# Yii Router FastRoute Adapter Change Log

## 4.0.4 under development

- no changes in this release.

## 4.0.3 December 13, 2025

- Enh #162: Add PHP 8.5 support (@vjik)

## 4.0.2 September 16, 2025

- Bug #160: Fix URL generation to skip `null` arguments (@vjik)

## 4.0.1 February 26, 2025

- Bug #159: Fix merging of query parameters with non-substituted arguments in `UrlGenerator` (@vjik)

## 4.0.0 February 25, 2025

- Chg #157: Bump minimal PHP version to 8.1 and minor refactoring (@vjik)
- Chg #158: Change PHP constraint in `composer.json` to `8.1 - 8.4` (@vjik)
- Chg #158: Adapt to `yiisoft/router` version `^4.0` (@vjik)
- Enh #154: Add `UrlGenerator` host and scheme properties to package config params (@ev-gor)
- Enh #158: Use FQN for built-in PHP functions (@vjik)

## 3.1.0 March 19, 2024

- New #144: Add optional default host and scheme to `UrlGenerator` (@vjik)
- Enh #139: Add support for `psr/http-message` version `^2.0` (@vjik)

## 3.0.1 December 24, 2023

- Bug #133, #136: Don't add not substituted arguments to a query string (@rustamwin, @vjik)
- Bug #134: Don't add query string if it's empty (@rustamwin)

## 3.0.0 February 17, 2023

- Chg #122: Adapt configuration group names to Yii conventions (@vjik)
- Enh #124: Add support of `yiisoft/router` version `^3.0` (@vjik)

## 2.1.0 January 09, 2023

- Chg #120: Update `yiisoft/router` version to `^2.1` (@rustamwin)

## 2.0.0 November 12, 2022

- Enh #105: Raise the minimum PHP version to 8.0 (@xepozz, @rustamwin)
- Enh #106: Add composer require checker into CI (@xepozz)
- Enh #108: Add `$queryParameters` parameter to `UrlGenerator::generateFromCurrent()` method (@rustamwin)
- Bug #107: Keep query string when generating from current route (@rustamwin)

## 1.1.1 June 28, 2022

- Enh #103: Add support of `psr/simple-cache` version `^2.0|^3.0` (@vjik)

## 1.1.0 June 27, 2022

- Chg #102: Update `yiisoft/router` version to `^1.1` (@rustamwin)
- Enh #100: Add support for multiple route hosts (@Gerych1984)

## 1.0.0 December 30, 2021

- Initial release.
