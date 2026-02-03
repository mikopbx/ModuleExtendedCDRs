# Исправление потери CDR-записей при синхронизации

## Проблема

Записи из основной таблицы `cdr_general` (Asterisk) не попадают в таблицу модуля.
Причина — `getHistoryData()` модифицирует `$this->cdrOffset` **по ссылке** до сохранения записей.
Если сохранение падает с исключением, in-memory offset уже продвинут, и при следующей итерации записи пропускаются навсегда.

## Затронутые файлы

- `Lib/HistoryParser.php` — метод `getHistoryData()`
- `bin/ConnectorDB.php` — методы `syncCdrData()`, `batchSaveCallHistory()`
- `bin/SyncRecords.php` — вызов `getHistoryData()` (адаптация к новому формату)
- `Lib/Mp3TagService.php` — ID3-теги и поддержка webm

---

## План исправлений

### 1. Убрать модификацию offset по ссылке в `getHistoryData()`
- [x] **Файл:** `Lib/HistoryParser.php`
- Сигнатура: `int &$offset` → `int $offset`
- Offset вычисляется локально в `$calculatedOffset`
- Возвращает `['data' => $resultRows, 'newOffset' => $calculatedOffset]`

### 2. Адаптировать `syncCdrData()` к новой сигнатуре
- [x] **Файл:** `bin/ConnectorDB.php`
- Offset из `getHistoryData()` сохраняется в `$parsedOffset`
- Применяется к `$this->cdrOffset` только **после** успешного `batchSaveCallHistory()`

### 2a. Адаптировать `SyncRecords::syncCdrData()`
- [x] **Файл:** `bin/SyncRecords.php`
- Извлечение `$historyResult['data']` вместо прямого результата

### 3. Добавить обработку ошибок в `batchSaveCallHistory()`
- [x] **Файл:** `bin/ConnectorDB.php`
- `$db->execute()` обёрнут в `try/catch` — при ошибке чанка логируем и продолжаем
- `$record->save()` обёрнут в `try/catch` — при ошибке UPDATE логируем и продолжаем
- Возвращается реальное количество успешных вставок/обновлений

### 4. Исправить sequential offset check
- [x] **Файл:** `bin/ConnectorDB.php`
- Sequential проверка стартует от `$oldOffset` (оригинальный offset до синхронизации)
- Итоговый offset = `max(sequentialMaxId, parsedOffset)`

### 5. Исправить Mp3TagService: поддержка webm + fix getid3 autoload
- [x] **Файл:** `Lib/Mp3TagService.php`
- Проверка расширения файла: ID3-теги пишутся только для `.mp3`
- Для `.webm` и прочих форматов — пропуск тегирования, только symlink
- Явная загрузка `getid3.php` перед `getid3_writetags` (fix autoload)
- Symlink создаётся с реальным расширением файла (не хардкод `.mp3`)

---

## Тестирование на serber@boffart.miko.ru

- [x] `php -l` для всех изменённых файлов — OK
- [x] Откат offset на 100, первая синхронизация без Mp3TagService fix:
  - Throwable `getid3.php MUST be included` → offset НЕ продвинулся (fix работает)
  - Повторная синхронизация через 10 сек — успешно (102 update)
- [x] Откат offset на 100, синхронизация С Mp3TagService fix:
  - Нет Throwable, Mp3TagsTime=0.03s, синхронизация с первой попытки
  - Offset корректно 625069 → 625169 (+100)
- [x] WebM: решено оставить без тегов, только symlink с читаемым именем
