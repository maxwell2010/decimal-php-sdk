# Decimal PHP SDK

Независимый SDK для Decimal от [MintCandy](https://mintcandy.ru/) и [@Maxwell2019](https://t.me/Maxwell2019). PHP 8.2+, MIT.

**v0.1.0-alpha.1 - preview. Полный паритет с Go/Python/C# пока не заявляется.** Реализованы 27 ABI-профилей, 577 функций, кошельки mnemonic/BIP32, EVM-подпись и offline Cosmos. Точная [матрица покрытия](docs/PARITY.md) отделяет кодеки от завершенных бизнес-сценариев.

## Установка

Скачайте архив из [GitHub Releases](https://github.com/maxwell2010/decimal-php-sdk/releases/tag/v0.1.0-alpha.1), распакуйте и выполните внутри каталога:

```sh
composer install --no-dev
php examples/offline.php
```

Требуются расширения curl, bcmath, gmp, mbstring. Пакет не опубликован в Packagist. Имя Composer - `mintcandy/decimal-web3-sdk`; GitHub служит источником распространения. В архив включен composer.lock. Подробные команды и примеры - в [English README](README.md).

В текущей Windows-среде gmp доступен, но выключен в php.ini:

```powershell
php -d extension=gmp C:/composer/composer.phar install
php -d extension=gmp tests/run.php
```

## Пример

```php
require 'vendor/autoload.php';
use Decimal\{Client, Network, Mnemonic, TransactionDraft, Units};

$client = new Client(Network::testnet());
$phrase = getenv('DECIMAL_MNEMONIC') ?: throw new RuntimeException('Set DECIMAL_MNEMONIC');
$recipient = getenv('RECIPIENT') ?: throw new RuntimeException('Set RECIPIENT');
$wallet = Mnemonic::wallet($phrase, index: 0);
echo Units::format($client->balance($wallet->address)), " DEL\n";
$draft = TransactionDraft::del($wallet->address, $recipient, '0.01');
$prepared = $client->prepare($draft);
$signed = $wallet->sign($prepared);
// Отправка остается отдельным явным действием приложения.
// $hash = $client->broadcast($signed);
```

Суммы и ABI-числа передаются десятичными строками. Не используйте float. Фразы и ключи храните вне исходников и логов. Mainnet - chain ID 75, testnet - 202020; клиент проверяет eth_chainId. Подготовка выполняет read RPC, подпись локальна.

Поддерживаются EIP-155, EIP-2930, EIP-1559, EIP-712, permit, Safe EOA signatures, allowance-планы, DEL multisend, события receipt, proxy/beacon, REST GET, WebSocket и 43 типа Cosmos-запросов на уровне схемы. **Schema encoding не заменяет ValidateBasic, роли, ante admission или интеграционный сценарий.** Отдельные ограничения перечислены в матрице покрытия.

Предупреждение Composer устранено удалением зависимостей с phpseclib. Текущий composer audit не находит известных advisories. Это не заменяет аудит криптографии.

Тесты используют независимые calldata/подписи Python и Cosmos-эталон Go. Транзакции в сеть при разработке не отправлялись. Реальные сетевые сценарии с расходованием средств не проверены.
