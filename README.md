# Postback Service

PHP 8.3 / Laravel 11 · MySQL 8 · Redis 7 · Docker Compose

## Технології

| Шар | Рішення |
|-----|---------|
| Backend | PHP 8.3, Laravel 11 |
| База даних | MySQL 8.0 |
| Дедуплікація | Redis 7 |
| HTTP-клієнт | Laravel `Http` (Guzzle) |
| Контейнеризація | Docker Compose |
| Mock партнера | MockServer |

## Запуск

```bash
git clone <repo-url> postback-service && cd postback-service

# Запуск всіх сервісів
docker compose up -d --build

# Міграції (після того як MySQL підніметься ~10 с)
docker compose exec app php artisan migrate --force
```

Сервіс доступний на http://localhost:8000

## API

### Прийом постбеку

```
GET /api/postback?click_id=clk_123&event=purchase&amount=49.90
```

**Успішна відповідь (200):**
```json
{"status":"accepted","message":"Event received"}
```

**Дублікат (200):**
```json
{"status":"duplicate","message":"Event already received"}
```

**Невалідні параметри (422):**
```json
{"status":"error","message":"Invalid parameters"}
```

### Перегляд подій

```
GET /api/postbacks
GET /api/postbacks?status=sent
GET /api/postbacks?status=failed
```

## Приклади тестування

```bash
# Новий постбек
curl "http://localhost:8000/api/postback?click_id=clk_123&event=purchase&amount=49.90"

# Дублікат (той самий click_id+event+amount)
curl "http://localhost:8000/api/postback?click_id=clk_123&event=purchase&amount=49.9"

# Невалідні параметри
curl "http://localhost:8000/api/postback?click_id=&event=purchase&amount=10"
curl "http://localhost:8000/api/postback?click_id=clk_1&event=purchase&amount=-5"

# Перегляд всіх подій
curl "http://localhost:8000/api/postbacks"

# Тільки відправлені
curl "http://localhost:8000/api/postbacks?status=sent"
```

## Дедуплікація

Ключ дедуплікації — SHA-256 від рядка `click_id|event|amount` (amount нормалізується до 4 знаків після коми, тому `49.9` і `49.90` — однаковий ключ).

Ключ зберігається в Redis з TTL 86400 секунд (24 години). Перевірка виконується **до** запису в MySQL, тому дублікати не потрапляють у базу.

## Повторні спроби надсилання

`PartnerService::send()` виконує до 3 спроб (перша + 2 повторні) у разі:
- мережевої помилки / таймауту (`ConnectionException`)
- HTTP 429, 5xx

HTTP 4xx (крім 429) — без повтору.

Результат (`sent` / `failed`) та кількість спроб зберігаються в таблиці `postbacks`.

## Діагностика: події не надходять до API партнера

1. **Перевірити статус подій у базі** — `GET /api/postbacks?status=failed`. Якщо записи є зі статусом `failed` — проблема у відправці, якщо відсутні — постбеки взагалі не надходять до сервісу.

2. **Перевірити логи контейнера** — `docker compose logs app`. Шукати WARNING/ERROR від `PartnerService`.

3. **Перевірити поле `partner_response`** у таблиці — там зберігається HTTP-відповідь або текст помилки (таймаут, connection refused тощо).

4. **Перевірити мережеву доступність** — з контейнера виконати `curl $PARTNER_API_URL`. Можливі причини: неправильний URL, firewall, партнер не відповідає.

5. **Перевірити конфігурацію** — переконатися що `PARTNER_API_URL` задано правильно в змінних середовища (`docker compose exec app env | grep PARTNER`).

6. **Перевірити дедуплікацію** — якщо тестуємо одним і тим же click_id/event/amount, Redis поверне дублікат і відправки не буде. Очистити ключ: `docker compose exec redis redis-cli FLUSHDB`.
