<?php
/*
 * MikoPBX - free phone system for small business
 * Copyright © 2017-2023 Alexey Portnov and Nikolay Beketov
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <https://www.gnu.org/licenses/>.
 */

namespace Modules\ModuleExtendedCDRs\Lib;

use MikoPBX\Core\System\Util;
use Modules\ModuleExtendedCDRs\Models\CallHistory;

class Mp3TagService
{
    private string $soxiPath = '';
    private string $mkdirPath = '';
    private string $lnPath = '';
    private string $coverImageData = '';
    private string $moduleDir;

    public function __construct(string $moduleDir)
    {
        $this->moduleDir = $moduleDir;
    }

    /**
     * Инициализирует кешированные пути и данные обложки при первом вызове.
     */
    private function initPaths(): void
    {
        if (empty($this->soxiPath)) {
            $this->soxiPath = Util::which('soxi');
            $this->mkdirPath = Util::which('mkdir');
            $this->lnPath = Util::which('ln');
        }
        if (empty($this->coverImageData)) {
            $coverImage = $this->moduleDir . '/public/assets/img/mikopbx-picture.jpg';
            $coverImageCustom = dirname($this->moduleDir) . '/ModuleExtendedCDRs-logo-mp3.jpg';
            if (file_exists($coverImageCustom)) {
                $coverImage = $coverImageCustom;
            }
            $this->coverImageData = file_get_contents($coverImage);
        }
    }

    /**
     * Устанавливает ID3-теги и создаёт symlink с человекочитаемым именем.
     * @param CallHistory $data
     * @return void
     */
    public function updateTags(CallHistory $data): void
    {
        if (!file_exists($data->recordingfile)) {
            return;
        }

        $this->initPaths();

        $formattedDate  = date('Y-m-d-H_i', strtotime($data->start));
        $uid            = str_replace('mikopbx-', '', $data->linkedid);
        $prettyFilename = "$uid-$formattedDate-$data->src_num-$data->dst_num";

        $ext = strtolower(pathinfo($data->recordingfile, PATHINFO_EXTENSION));
        if ($ext === 'mp3') {
            $this->writeId3Tags($data->recordingfile, $prettyFilename, $data->start);
        }

        $this->createPrettyLink($data->recordingfile, $prettyFilename, $ext);
    }

    /**
     * Записывает ID3-теги в MP3-файл.
     * @param string $filename
     * @param string $prettyFilename
     * @param string $start
     * @return void
     */
    private function writeId3Tags(string $filename, string $prettyFilename, string $start): void
    {
        // getid3_writetags требует предварительной загрузки основного класса getID3
        if (!class_exists('getID3', false)) {
            $getid3Path = dirname(__DIR__) . '/vendor/james-heinrich/getid3/getid3/getid3.php';
            if (file_exists($getid3Path)) {
                require_once $getid3Path;
            }
        }

        $tagWriter = new \getid3_writetags();
        $tagWriter->filename          = $filename;
        $tagWriter->tagformats        = ['id3v2.3'];
        $tagWriter->overwrite_tags    = true;
        $tagWriter->tag_encoding      = 'UTF-8';
        $tagWriter->remove_other_tags = false;

        $tagWriter->tag_data = [
            'title'   => [$prettyFilename],
            'attached_picture' => [
                [
                    'data' => $this->coverImageData,
                    'picturetypeid' => 0x03,
                    'description' => 'MikoPBX',
                    'mime' => 'image/jpeg'
                ]
            ],
            'comment' => [md5($prettyFilename . '_' . trim(shell_exec("$this->soxiPath " . escapeshellarg($filename)) ?? ''))],
            'year'    => [date('Y', strtotime($start))],
        ];
        $tagWriter->WriteTags();
        unset($tagWriter);
    }

    /**
     * Создаёт symlink с человекочитаемым именем в pretty-monitor.
     * @param string $recordingFile
     * @param string $prettyFilename
     * @param string $ext
     * @return void
     */
    private function createPrettyLink(string $recordingFile, string $prettyFilename, string $ext): void
    {
        $dirLink = str_replace('/monitor/', '/pretty-monitor/', dirname($recordingFile, 2));
        $escapedDir = escapeshellarg($dirLink);
        $escapedSrc = escapeshellarg($recordingFile);
        $escapedDst = escapeshellarg("$dirLink/$prettyFilename.$ext");
        shell_exec("$this->mkdirPath -p $escapedDir; $this->lnPath -s $escapedSrc $escapedDst > /dev/null 2> /dev/null");
    }
}
