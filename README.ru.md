# Decimal PHP SDK

Независимый SDK для Decimal от [MintCandy](https://mintcandy.ru/) и [@Maxwell2019](https://t.me/Maxwell2019). PHP 8.2+, MIT.

**v0.1.6 - выпуск по результатам live-аудита mainnet/testnet с улучшенной диагностикой RPC.** Включены 28 ABI-профилей, 654 функции, кошельки mnemonic/BIP32, EVM-подпись, проверяемые сценарии Safe/NFT/staking/checks и Cosmos validation/signing/query. [Матрица покрытия](docs/PARITY.md) фиксирует поддерживаемые интерфейсы и границы совместимости.

[Текущий паритет API](docs/CURRENT_PARITY_0_1_4.md) содержит соответствия всех 55 операций Go/Python/C#. В текущем релизе проходят 8095 offline-проверок. Добавлены сервисы token economy, staking/validators, NFT, bridge и checks. Небезопасный ERC20 allowance для generic Multicall остается отключенным; доступна проверенная альтернатива RestrictedMultisend.

[Live-аудит mainnet/testnet](docs/LIVE_RPC_AUDIT_0_1_5.md): 429 сохраненных RPC-попыток, 159 успешных проверок, реальные eth_call и улучшенная диагностика cURL/HTTP. Транзакции в сеть не отправлялись.


[Bridge и расширенный NFT-аудит](docs/BRIDGE_NFT_AUDIT_0_1_6.md): проверены кандидат Bridge и реальные NFT eth_call; добавлена проверяемая привязка Bridge перед approval-планом. Mainnet Bridge и полный ABI testnet пока не подтверждены; выход одной зрелой legacy NFT-позиции остается с revert.

## Последняя стабильная версия одной командой

```sh
composer create-project --repository=https://github.com/maxwell2010/decimal-php-sdk/releases/latest/download/packages.json mintcandy/decimal-web3-sdk decimal-php-sdk
```

Постоянная ссылка `packages.json` выбирает последний стабильный релиз и устанавливает зависимости. Команда создает новый каталог. Требуются Composer 2 и расширения PHP curl, bcmath, gmp, mbstring. Git не требуется, если Composer может распаковать ZIP через расширение PHP zip либо unzip/7z.

[Постоянная ссылка на ZIP](https://github.com/maxwell2010/decimal-php-sdk/releases/latest/download/decimal-php-sdk.zip) тоже ведет на последний стабильный релиз. Контрольные суммы доступны по [SHA256SUMS](https://github.com/maxwell2010/decimal-php-sdk/releases/latest/download/SHA256SUMS). Установленный проект сохраняет свою версию через lockfile; обновления не выполняются самопроизвольно.

Для существующего приложения зарегистрируйте тот же URL `packages.json` как Composer repository, подключите `mintcandy/decimal-web3-sdk:^0.1` и обновляйте совместимые версии командой `composer update mintcandy/decimal-web3-sdk --with-dependencies`. Пакет не опубликован в Packagist: источником распространения служит GitHub.

## Установка из архива

Скачайте ZIP из [GitHub Releases](https://github.com/maxwell2010/decimal-php-sdk/releases/latest), распакуйте и выполните внутри каталога:

```sh
composer install --no-dev
php examples/offline.php
```

В текущей Windows-среде gmp доступен, но выключен в php.ini:

```powershell
php -d extension=gmp C:/composer/composer.phar create-project --repository=https://github.com/maxwell2010/decimal-php-sdk/releases/latest/download/packages.json mintcandy/decimal-web3-sdk decimal-php-sdk
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

Поддерживаются EIP-155/2930/1559, contract creation, EIP-712, checked permit, внешние signers с проверкой неизменности транзакции, allowance-планы, DEL multisend, weighted Safe с EOA/EIP-1271/prevalidated signatures, NFT/staking-проверки и события receipt. Runtime и implementation привязываются к trusted evidence через `VerifiedContract`. Файловый `NonceManager` координирует явное резервирование nonce между процессами; потеря/повреждение хранилища требует сверки с сетью, а не автоматического сброса.

Для Cosmos есть 393 схемы, protobuf decode, все 43 закрепленные ValidateBasic-политики, проверка порядка подписантов, direct-signing, fee convergence и gRPC query/simulation. Используйте `signValidated` для stateless-проверок, `signChecked` для проверки порядка подписантов. Низкоуровневый schema codec не заменяет бизнес-валидацию. Роли, ante admission и успешное исполнение определяет нода.

Предупреждение Composer устранено удалением зависимостей с phpseclib. Текущий composer audit не находит известных advisories. Тесты используют независимые calldata/подписи Python, схемы и Cosmos-эталоны Go/ноды, loopback-транспорты и установку из собранного архива. Транзакции в сеть при разработке не отправлялись.

[English README](README.md) | [Примеры](docs/EXAMPLES.md) | [Cosmos validation](docs/COSMOS_VALIDATION.md) | [EVM scope](docs/EVM_SCOPE.md)

[Итоги аудита и паритет операций](docs/FINAL_AUDIT_0_1_1.md).
