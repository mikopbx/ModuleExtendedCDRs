#!/bin/sh
# Сброс offset CDR-синхронизации на начало указанного дня и перезапуск ConnectorDB.
# Использование:
#   reset-cdr-offset.sh           — сброс на начало текущего дня
#   reset-cdr-offset.sh 2026-01-15 — сброс на начало указанной даты

DATE="${1:-$(date '+%Y-%m-%d')}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

CDR_DB="/storage/usbdisk1/mikopbx/astlogs/asterisk/cdr.db"
MODULE_DB="/storage/usbdisk1/mikopbx/custom_modules/ModuleExtendedCDRs/db/module.db"

# Находим минимальный id на указанную дату
MIN_ID=$(sqlite3 "$CDR_DB" "SELECT MIN(id) FROM cdr_general WHERE start >= '${DATE} 00:00:00';")

if [ -z "$MIN_ID" ]; then
    echo "Нет записей за $DATE в cdr_general"
    exit 1
fi

NEW_OFFSET=$((MIN_ID - 1))
OLD_OFFSET=$(sqlite3 "$MODULE_DB" "SELECT cdrOffset FROM m_ModuleExtendedCDRs LIMIT 1;")

echo "Дата:   $DATE"
echo "Offset: $OLD_OFFSET -> $NEW_OFFSET (min id=$MIN_ID)"

# Обновляем offset
sqlite3 "$MODULE_DB" "UPDATE m_ModuleExtendedCDRs SET cdrOffset=$NEW_OFFSET;"

# Убиваем ConnectorDB
PID=$(busybox ps -o pid,args | grep 'ModuleExtendedCDRs.bin.ConnectorDB' | grep -v grep | awk '{print $1}')
if [ -n "$PID" ]; then
    echo "Kill ConnectorDB PID=$PID"
    busybox kill -9 $PID
    sleep 1
fi

# Запускаем safe.php — он поднимет ConnectorDB
echo "Starting safe.php..."
/usr/bin/php -f "$SCRIPT_DIR/safe.php"
echo "Done"
