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

1. Перевірю статус подій в базі, чи взагалі постбеки до сервісу доходять чи ні
2. Перевірю логи контейнеру
3. Перевірю мережеву доступність партнерки
4. Перевірити конфігурацію партнерки в env
