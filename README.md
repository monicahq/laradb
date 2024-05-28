# Browse your database straight from your Laravel application

This Laravel package lets your browse your database from your existing application. It creates a new route `/db`, which will show all your database and let you inspect it.

This tool is not meant to replace existing, more powerful desktop applications which do much more. Sometimes you simply don't need those tools at tool - sometimes you just want to quickly inspect what's in your database.

At the moment, it's not possible to edit data.

## Installation and usage

This package requires PHP 8.3 and Laravel 11 or higher. It supports all the DBMS supported by Laravel.

To install the package, run this command:

```
composer require monicahq/laradb
```

Once installed, open the `/db` route from your application, and you should see your database.

### Using an older version of PHP?

Since we stick with the current version of Laravel, the older PHP version we support is PHP 8.3.

## Useful information

- This package uses [TailwindCSS](https://tailwindcss.com/) to help with the design, served with the CDN version. This means these files won't be added to your CSS pipeline on your application.
- The package will only run when the .env variable APP_ENV is set to `local` in your .env file.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [Alexis Saettler](https://github.com/asbiin)
- [Regis Freyd](https://github.com/djaiss)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

