<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitc74062b0af66d24d8cf90c55497955eb
{
    public static $prefixLengthsPsr4 = array (
        'R' => 
        array (
            'Rtrs\\' => 5,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Rtrs\\' => 
        array (
            0 => __DIR__ . '/../..' . '/app',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'Rtrs\\Controllers\\Admin\\Activation' => __DIR__ . '/../..' . '/app/Controllers/Admin/Activation.php',
        'Rtrs\\Controllers\\Admin\\AdminController' => __DIR__ . '/../..' . '/app/Controllers/Admin/AdminController.php',
        'Rtrs\\Controllers\\Admin\\AdminSettings' => __DIR__ . '/../..' . '/app/Controllers/Admin/AdminSettings.php',
        'Rtrs\\Controllers\\Admin\\Meta\\AddMetaBox' => __DIR__ . '/../..' . '/app/Controllers/Admin/Meta/AddMetaBox.php',
        'Rtrs\\Controllers\\Admin\\Meta\\AffiliateOptions' => __DIR__ . '/../..' . '/app/Controllers/Admin/Meta/AffiliateOptions.php',
        'Rtrs\\Controllers\\Admin\\Meta\\MetaController' => __DIR__ . '/../..' . '/app/Controllers/Admin/Meta/MetaController.php',
        'Rtrs\\Controllers\\Admin\\Meta\\MetaOptions' => __DIR__ . '/../..' . '/app/Controllers/Admin/Meta/MetaOptions.php',
        'Rtrs\\Controllers\\Admin\\Meta\\SingleMetaOptions' => __DIR__ . '/../..' . '/app/Controllers/Admin/Meta/SingleMetaOptions.php',
        'Rtrs\\Controllers\\Admin\\Notifications' => __DIR__ . '/../..' . '/app/Controllers/Admin/Notifications.php',
        'Rtrs\\Controllers\\Admin\\RegisterPostType' => __DIR__ . '/../..' . '/app/Controllers/Admin/RegisterPostType.php',
        'Rtrs\\Controllers\\Admin\\ReviewSettings' => __DIR__ . '/../..' . '/app/Controllers/Admin/ReviewSettings.php',
        'Rtrs\\Controllers\\Admin\\ReviewTable' => __DIR__ . '/../..' . '/app/Controllers/Admin/ReviewTable.php',
        'Rtrs\\Controllers\\Admin\\ScriptLoader' => __DIR__ . '/../..' . '/app/Controllers/Admin/ScriptLoader.php',
        'Rtrs\\Controllers\\Ajax\\AjaxController' => __DIR__ . '/../..' . '/app/Controllers/Ajax/AjaxController.php',
        'Rtrs\\Controllers\\Ajax\\Migration' => __DIR__ . '/../..' . '/app/Controllers/Ajax/Migration.php',
        'Rtrs\\Controllers\\Ajax\\Review' => __DIR__ . '/../..' . '/app/Controllers/Ajax/Review.php',
        'Rtrs\\Controllers\\Ajax\\Shortcode' => __DIR__ . '/../..' . '/app/Controllers/Ajax/Shortcode.php',
        'Rtrs\\Controllers\\Marketing\\Offer' => __DIR__ . '/../..' . '/app/Controllers/Marketing/Offer.php',
        'Rtrs\\Controllers\\Marketing\\Review' => __DIR__ . '/../..' . '/app/Controllers/Marketing/Review.php',
        'Rtrs\\Controllers\\Shortcodes' => __DIR__ . '/../..' . '/app/Controllers/Shortcodes.php',
        'Rtrs\\Helpers\\Functions' => __DIR__ . '/../..' . '/app/Helpers/Functions.php',
        'Rtrs\\Hooks\\Backend' => __DIR__ . '/../..' . '/app/Hooks/Backend.php',
        'Rtrs\\Hooks\\Frontend' => __DIR__ . '/../..' . '/app/Hooks/Frontend.php',
        'Rtrs\\Hooks\\SeoHooks' => __DIR__ . '/../..' . '/app/Hooks/SeoHooks.php',
        'Rtrs\\Models\\Field' => __DIR__ . '/../..' . '/app/Models/Field.php',
        'Rtrs\\Models\\Review' => __DIR__ . '/../..' . '/app/Models/Review.php',
        'Rtrs\\Models\\Schema' => __DIR__ . '/../..' . '/app/Models/Schema.php',
        'Rtrs\\Models\\SettingsAPI' => __DIR__ . '/../..' . '/app/Models/SettingsAPI.php',
        'Rtrs\\Shortcodes\\ReviewAvgRating' => __DIR__ . '/../..' . '/app/Shortcodes/ReviewAvgRating.php',
        'Rtrs\\Shortcodes\\ReviewSchema' => __DIR__ . '/../..' . '/app/Shortcodes/ReviewSchema.php',
        'Rtrs\\Traits\\SingletonTrait' => __DIR__ . '/../..' . '/app/Traits/SingletonTrait.php',
        'Rtrs\\Widgets\\ReviewSchema' => __DIR__ . '/../..' . '/app/Widgets/ReviewSchema.php',
        'Rtrs\\Widgets\\Widget' => __DIR__ . '/../..' . '/app/Widgets/Widget.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitc74062b0af66d24d8cf90c55497955eb::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitc74062b0af66d24d8cf90c55497955eb::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInitc74062b0af66d24d8cf90c55497955eb::$classMap;

        }, null, ClassLoader::class);
    }
}
