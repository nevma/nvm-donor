<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit8c4b4954924e428c1d23e9c79eacd9ab
{
    public static $prefixLengthsPsr4 = array (
        'N' => 
        array (
            'Nvm\\Donor\\' => 10,
        ),
        'D' => 
        array (
            'Dealerdirect\\Composer\\Plugin\\Installers\\PHPCodeSniffer\\' => 55,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Nvm\\Donor\\' => 
        array (
            0 => __DIR__ . '/../../..' . '/classes',
        ),
        'Dealerdirect\\Composer\\Plugin\\Installers\\PHPCodeSniffer\\' => 
        array (
            0 => __DIR__ . '/..' . '/dealerdirect/phpcodesniffer-composer-installer/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit8c4b4954924e428c1d23e9c79eacd9ab::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit8c4b4954924e428c1d23e9c79eacd9ab::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit8c4b4954924e428c1d23e9c79eacd9ab::$classMap;

        }, null, ClassLoader::class);
    }
}
