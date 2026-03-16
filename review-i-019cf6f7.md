# Огляд змін: i-019cf6f7-ea44-73e7-8d18-90d65ee23268

Міграція HTTP-шару з Slim 2 на Mezzio/PSR-15.
Гілка з такою самою назвою є в кожному репозиторії де були зміни.

---

## src/atrocore — гілка `i-019cf6f7-ea44-73e7-8d18-90d65ee23268`

### Нові файли
| Файл | Опис |
|------|------|
| `app/Atro/Core/Routing/Route.php` | PHP 8 атрибут `#[Route]` для анотування PSR-15 хендлерів |
| `app/Atro/Core/Factories/HttpPipeline.php` | Фабрика PSR-15 пайплайну (замість Slim): реєструє маршрути, збирає `MiddlewarePipe` |
| `app/Atro/Core/Http/RequestWrapper.php` | Сумісна обгортка над `ServerRequestInterface` зі Slim-методами (`get()`, `isGet()` тощо) для legacy-контролерів |
| `app/Atro/Core/Middleware/AuthMiddleware.php` | PSR-15 middleware автентифікації (замість `Slim\Middleware`) |
| `app/Atro/Core/Middleware/LegacyControllerHandler.php` | PSR-15 міст до старих контролерів через `ControllerManager` |
| `app/Atro/Handlers/Dashlet/DashletHandler.php` | Перший PSR-15 хендлер: `GET /api/v1/Dashlet/{dashletName}` |

### Змінені файли
| Файл | Що змінилось |
|------|--------------|
| `app/Atro/Core/Application.php` | `runApi()` замінено на PSR-15 пайплайн; `runInstallerApi()` та `runEntryPoint()` переписані без Slim |
| `app/Atro/Core/Container/ServiceManagerConfig.php` | Видалено `slim`, додано `MiddlewarePipe::class => HttpPipeline::class` |
| `app/Atro/Core/ControllerManager.php` | Сигнатура `process()` — `ServerRequestInterface` замість Slim Request/Response; передає `RequestWrapper` у контролери |
| `app/Atro/Core/Slim/Validator.php` | `validateRequest/validateResponse` тепер приймають PSR-7 об'єкти напряму |
| `app/Atro/Resources/routes.json` | Видалено маршрут `Dashlet/:dashletName` (мігровано до хендлера) |
| `composer.json` | Додано залежності `mezzio/mezzio` та `mezzio/mezzio-fastroute` |

### Видалені файли
| Файл | Причина |
|------|---------|
| `app/Atro/Core/Attribute/Route.php` | Перейменовано в `Atro\Core\Routing\Route` |

---

## src/atrocore-legacy — гілка `i-019cf6f7-ea44-73e7-8d18-90d65ee23268`

### Змінені файли
| Файл | Що змінилось |
|------|--------------|
| `app/Espo/Core/Utils/Auth.php` | Замінено `Slim\Http\Request` на `ServerRequestInterface`; `$_SERVER['REMOTE_ADDR']` захищено від CLI-контексту (`?? ''`) |

---

## src/reports — гілка `i-019cf6f7-ea44-73e7-8d18-90d65ee23268`

### Нові файли
| Файл | Опис |
|------|------|
| `app/Handlers/Report/ReportDashletHandler.php` | PSR-15 хендлер для `GET /api/v1/Report/action/dashletData` та `GET /api/v1/Report/action/dashletList` |

### Змінені файли
| Файл | Що змінилось |
|------|--------------|
| `app/Controllers/Report.php` | Видалено `actionDashletData` і `actionDashletList` (замінено хендлером) |
| `app/DashletType/DashletTypeInterface.php` | `dashletList()` — тип `Request` → `ServerRequestInterface` |
| `app/DashletType/Crosstab.php` | Те саме, що вище |
| `app/DashletType/Summary.php` | Те саме + `$request->get()` → `$request->getQueryParams()` |
| `app/Services/Report.php` | `dashletList()` — тип `Request` → `ServerRequestInterface` |

---

## Що перевірити

- [ ] `GET /api/v1/Dashlet/{dashletName}` — відповідь дашлету
- [ ] `GET /api/v1/Report/action/dashletData?reportId=...` — дані репорту
- [ ] `GET /api/v1/Report/action/dashletList?reportId=...` — список з сортуванням (`sortBy`, `asc`)
- [ ] Авторизація: перевірити що 401 повертається без токена
- [ ] Крон-задачі: переконатись що немає помилок в логах (раніше падали через `slim not found`)
- [ ] Стандартні CRUD операції для `Report` (успадковані від `Base`)
