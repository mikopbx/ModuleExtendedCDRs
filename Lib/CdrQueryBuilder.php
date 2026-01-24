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

use Modules\ModuleExtendedCDRs\bin\ConnectorDB;

class CdrQueryBuilder
{
    private array $conditions = [];
    private array $bindParams = [];

    /**
     * Добавляет условие фильтрации по временному диапазону.
     * @param string $start
     * @param string $end
     * @return self
     */
    public function whereDateRange(string $start, string $end): self
    {
        $this->conditions[] = "cdr_general.start BETWEEN :start AND :end";
        $this->bindParams[':start'] = $start;
        $this->bindParams[':end'] = $end;
        return $this;
    }

    /**
     * Добавляет условие фильтрации по номерам (srcIndex/dstIndex).
     * @param array $numbers
     * @param string $prefix
     * @return self
     */
    public function whereNumbers(array $numbers, string $prefix = 'Index'): self
    {
        if (empty($numbers)) {
            return $this;
        }
        foreach ($numbers as $value) {
            $this->bindParams[":$prefix$value"] = $value;
        }
        $placeholders = implode(', ', array_map(static function ($value) use ($prefix) {
            return ":$prefix$value";
        }, $numbers));
        $this->conditions[] = "(cdr_general.dstIndex IN ($placeholders) OR cdr_general.srcIndex IN ($placeholders))";
        return $this;
    }

    /**
     * Добавляет условие фильтрации по расширениям из additionalFilter.
     * @param array $additionalFilter
     * @return self
     */
    public function whereFilteredExtensions(array $additionalFilter): self
    {
        $extFilter = $additionalFilter['bind']['filteredExtensions'] ?? [];
        if (empty($extFilter)) {
            return $this;
        }
        foreach ($extFilter as &$value) {
            $value = ConnectorDB::getPhoneIndex($value);
            $this->bindParams[":IndexAdd$value"] = $value;
        }
        unset($value);
        $placeholders = implode(', ', array_map(static function ($value) {
            return ":IndexAdd$value";
        }, $extFilter));
        $conditionStr = str_replace(
            ['{filteredExtensions:array}', 'dst_num', 'src_num', 'AND ()'],
            [$placeholders, 'cdr_general.dstIndex', 'cdr_general.srcIndex', ''],
            $additionalFilter['conditions'] ?? ''
        );
        if (!empty(trim($conditionStr))) {
            $this->conditions[] = $conditionStr;
        }
        return $this;
    }

    /**
     * Добавляет условие фильтрации по linkedid.
     * @param array $ids
     * @return self
     */
    public function whereLinkedIds(array $ids): self
    {
        if (empty($ids)) {
            return $this;
        }
        $index = 0;
        foreach ($ids as $linkedid) {
            $this->bindParams[":linkedid$index"] = $linkedid;
            $index++;
        }
        $placeholders = implode(', ', array_map(static function ($key) {
            return ":linkedid$key";
        }, array_keys($ids)));
        $this->conditions[] = "cdr_general.linkedid IN ($placeholders)";
        return $this;
    }

    /**
     * Добавляет условие минимального billsec.
     * @param int $minBilSec
     * @return self
     */
    public function whereMinBillSec(int $minBilSec): self
    {
        if ($minBilSec > 0) {
            $this->bindParams[':minBilSec'] = $minBilSec;
            $this->conditions[] = "billsec > :minBilSec";
        }
        return $this;
    }

    /**
     * Возвращает итоговое условие WHERE.
     * @return string
     */
    public function getCondition(): string
    {
        return implode(' AND ', $this->conditions);
    }

    /**
     * Возвращает bind-параметры.
     * @return array
     */
    public function getBindParams(): array
    {
        return $this->bindParams;
    }
}
