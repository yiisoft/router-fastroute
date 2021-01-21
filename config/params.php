<?php

declare(strict_types=1);

return [
    'yiisoft/router' => [
        'enableCache' => true,

        /**
         * Yii Framework encodes URLs differently than previous versions. If you are
         * migrating a project from older versions, you can set this value to `true`
         * to keep URLs encoded the same way.
         * Default `false` is RFC 3986 compliant
         */
        'yii2Compat' => false,
    ],
];
