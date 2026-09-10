<?php

declare(strict_types=1);
namespace Modules\ModuleExtendedCDRs\Lib;

use RuntimeException;
use Throwable;

/** Filesystem queue/cache. Locks are persistent: never unlink an inode another process can hold. */
final class RecordingArchiveJobs
{
    private string $root;
    private int $ttl;
    public function __construct(string $root, int $ttl = 900)
    {
        $this->root = rtrim($root, '/');
        $this->ttl = $ttl;
        if (!is_dir($this->root) && !mkdir($this->root, 0700, true) && !is_dir($this->root)) {
            throw new RuntimeException('archive_storage_unavailable');
        }
    }

    public function request(string $owner, array $input, string $revision, callable $launch): array
    {
        $lock = $this->lock('registry');
        try {
            $this->clean();
            $key = hash('sha256', $owner . json_encode($input));
            $pending = 0;
            foreach ($this->all() as $job) {
                $job = $this->refresh($job);
                if (in_array($job['state'], ['queued', 'building'], true)) $pending++;
                if ($job['key'] !== $key) continue;
                if (in_array($job['state'], ['queued', 'building'], true)
                    || ($job['state'] === 'ready' && $job['revision'] === $revision && $this->fresh($job))) {
                    return $this->publicState($job);
                }
            }
            if ($pending >= 8) throw new RuntimeException('archive_queue_full');
            $id = bin2hex(random_bytes(24));
            $job = ['id'=>$id, 'key'=>$key, 'owner'=>$owner, 'input'=>$input, 'revision'=>$revision,
                'state'=>'queued', 'created'=>time(), 'updated'=>time(), 'completed'=>0, 'total'=>0,
                'secret'=>bin2hex(random_bytes(32)), 'manifest'=>[], 'error'=>null];
            $this->save($job);
            try { $launch($id); } catch (Throwable $e) {
                $job['state']='failed'; $job['error']='archive_start_failed'; $this->save($job);
                throw new RuntimeException('archive_start_failed', 0, $e);
            }
            return $this->publicState($job);
        } finally { $this->unlock($lock); }
    }

    /** Worker keeps its job lock while waiting for the global I/O lock. */
    public function run(string $id, callable $build): bool
    {
        $this->validateId($id);
        $lock = $this->lock($id);
        if ($lock === false) return false;
        $buildLock = null;
        try {
            $job = $this->load($id);
            if ($job['state'] !== 'queued') return false;
            $buildLock = $this->lock('builder');
            $job['state']='building'; $this->save($job);
            try {
                $lastProgress=0.0;
                $manifest = $build($job['input'], function (int $completed, int $total) use (&$job, &$lastProgress): void {
                    $job['completed']=$completed; $job['total']=$total;
                    if (microtime(true)-$lastProgress>=0.5 || $completed===$total) {
                        $this->save($job); $lastProgress=microtime(true);
                    }
                }, $this->root.'/'.$id.'.tar');
                if (!is_file($this->root.'/'.$id.'.tar')) throw new RuntimeException('archive_build_failed');
                $job['manifest']=$manifest;
                $job['state']='ready'; $job['expires']=time()+$this->ttl;
                $job['bytes']=filesize($this->root.'/'.$id.'.tar');
            } catch (Throwable $e) {
                @unlink($this->root.'/'.$id.'.tar');
                $this->removeWork($id);
                $job['state']='failed';
                $allowed=['archive_has_no_valid_entries','archive_too_large'];
                $job['error']=in_array($e->getMessage(), $allowed, true) ? $e->getMessage() : 'archive_build_failed';
            }
            $this->save($job);
            return true;
        } finally {
            if ($buildLock !== null) $this->unlock($buildLock);
            $this->unlock($lock);
        }
    }

    public function status(string $id, string $owner): array
    {
        $job=$this->owned($id,$owner);
        $job=$this->refresh($job);
        if ($job['state']==='ready' && !$this->fresh($job)) throw new RuntimeException('archive_expired');
        return $this->publicState($job);
    }

    public function ticket(string $id, string $owner): string
    {
        $job=$this->owned($id,$owner);
        if ($job['state']!=='ready' || !$this->fresh($job)) throw new RuntimeException('archive_expired');
        $expires=min(time()+120,$job['expires']);
        return $expires.'.'.hash_hmac('sha256',$id.'.'.$expires,$job['secret']);
    }

    /** Open under registry lock; an already-open Unix file survives later cache eviction. */
    public function openDownload(string $id, string $ticket)
    {
        $lock=$this->lock('registry');
        try {
            $job=$this->load($id);
            $parts=explode('.',$ticket);
            if (count($parts)!==2 || !ctype_digit($parts[0]) || (int)$parts[0]<time()
                || (int)$parts[0]>time()+120 || $job['state']!=='ready'
                || !hash_equals(hash_hmac('sha256',$id.'.'.$parts[0],$job['secret']),$parts[1])
                || !$this->fresh($job)) throw new RuntimeException('archive_invalid_ticket');
            $fp=fopen($this->root.'/'.$id.'.tar','rb');
            if ($fp===false) throw new RuntimeException('archive_expired');
            return $fp;
        } finally { $this->unlock($lock); }
    }

    public function cleanup(): void
    {
        $lock=$this->lock('registry');
        try { $this->clean(); } finally { $this->unlock($lock); }
    }

    private function clean(): void
    {
        $jobs=$this->all();
        usort($jobs,static function($a,$b){return $b['created']<=>$a['created'];});
        $bytes=0;
        foreach ($jobs as $job) {
            $bytes+=(int)($job['bytes']??0);
            $expired=($job['expires']??($job['created']+3600))<time();
            if (!$expired && $bytes<=4294967296) continue;
            $lock=$this->lock($job['id'],false);
            if ($lock===false) continue;
            try {
                @unlink($this->root.'/'.$job['id'].'.tar');
                @unlink($this->root.'/'.$job['id'].'.json');
                $this->removeWork($job['id']);
            } finally { $this->unlock($lock); }
        }
    }

    private function removeWork(string $id): void
    {
        $this->validateId($id);
        $dir=$this->root.'/'.$id.'.tar.work';
        foreach (glob($dir.'/*.tar')?:[] as $path) @unlink($path);
        if (is_dir($dir)) @rmdir($dir);
    }

    private function fresh(array $job): bool
    {
        if (($job['expires']??0)<time() || !is_file($this->root.'/'.$job['id'].'.tar')) return false;
        foreach ($job['manifest'] as $path=>$stamp) {
            clearstatcache(true,$path);
            $stat=@stat($path);
            if ($stat===false || [$stat['ino'],$stat['size'],$stat['mtime'],$stat['ctime']]!==$stamp) return false;
        }
        return true;
    }

    private function refresh(array $job): array
    {
        if (!in_array($job['state'],['queued','building'],true)) return $job;
        $lock=$this->lock($job['id'],false);
        if ($lock===false) return $job;
        try {
            $job=$this->load($job['id']);
            if ($job['state']==='building' || ($job['state']==='queued' && $job['created']<time()-30)) {
                $job['state']='failed'; $job['error']='archive_worker_stopped'; $this->save($job);
            }
            return $job;
        } finally { $this->unlock($lock); }
    }

    private function owned(string $id,string $owner): array
    {
        $job=$this->load($id);
        if (!hash_equals($job['owner'],$owner)) throw new RuntimeException('archive_not_found');
        return $job;
    }
    private function publicState(array $job): array
    {
        return array_intersect_key($job,array_flip(['id','state','completed','total','error','bytes','expires']));
    }
    private function all(): array
    {
        $jobs=[];
        foreach (glob($this->root.'/*.json')?:[] as $path) {
            $job=json_decode((string)file_get_contents($path),true);
            if (is_array($job) && isset($job['id'])) $jobs[]=$job;
        }
        return $jobs;
    }
    private function load(string $id): array
    {
        $this->validateId($id);
        $job=json_decode((string)@file_get_contents($this->root.'/'.$id.'.json'),true);
        if (!is_array($job)) throw new RuntimeException('archive_not_found');
        return $job;
    }
    private function save(array &$job): void
    {
        $job['updated']=time();
        $path=$this->root.'/'.$job['id'].'.json';
        $tmp=$path.'.'.bin2hex(random_bytes(6));
        if (file_put_contents($tmp,json_encode($job,JSON_THROW_ON_ERROR))===false || !rename($tmp,$path)) {
            @unlink($tmp); throw new RuntimeException('archive_storage_unavailable');
        }
        chmod($path,0600);
    }
    private function validateId(string $id): void
    {
        if (!preg_match('/^[a-f0-9]{48}$/D',$id)) throw new RuntimeException('archive_not_found');
    }
    private function lock(string $name,bool $wait=true)
    {
        // A bounded set of lock files avoids ever unlinking a live lock inode.
        $bucket=in_array($name,['registry','builder'],true)?$name:'job-'.substr($name,0,3);
        $fp=fopen($this->root.'/'.$bucket.'.lock','c');
        if ($fp===false) throw new RuntimeException('archive_storage_unavailable');
        // Root cron cleanup must not leave locks unwritable by the web worker.
        if (function_exists('posix_geteuid') && posix_geteuid()===0) {
            chown($this->root.'/'.$bucket.'.lock',fileowner($this->root));
            chgrp($this->root.'/'.$bucket.'.lock',filegroup($this->root));
        }
        if (!flock($fp,LOCK_EX|($wait?0:LOCK_NB))) {fclose($fp);return false;}
        return $fp;
    }
    private function unlock($fp): void {flock($fp,LOCK_UN);fclose($fp);}
}
