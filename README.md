# FS-TASKER

Provides a sequential task runner. Tasks and configuration is provided through a simple yaml config file.

Provides task plugins for:
* Combine
* Copy
* Create
* Empty
* Exec
* Replace Text
* Revision
* SASS

This package has been developed by H&H|Digital, an Australian botique developer. Visit us at [hnh.digital](http://hnh.digital).

## Requirements

* PHP 7.2.5 (min)

## Installation

Via commmand line:

`bash <(curl -s https://hnhdigital-os.github.io/fs-tasker/builds/install)`

Download the latest build:

`curl -o ./fs-tasker -LS https://github.com/hnhdigital-os/fs-tasker/raw/master/builds/fs-tasker`
`chmod a+x ./fs-tasker`

Move it local bin:

`mv ./fs-tasker /usr/local/bin/fs-tasker`

## Updating

This tool provides a self-update mechanism. Simply run the self-update command.

`fs-tasker self-update`

## How to use

```
fs-tasker <command> [options] [arguments]
```

## Config (.tasker.config.yml)

An example of the tasker configuration using YAML.

```yaml
tasker:
  version: 1.1.2

environments:
  - dev
  - staging
  - prod

options:

aliases:
  SASS: resources/assets/sass
  NODE: node_modules
  ASSETS: public/assets
  BUILD: public/build
  VENDOR: vendor
  RESOURCES: resources
  VIEWS: resources/views
  IMAGES: resources/assets/images
  VENDOR_ASSETS: resources/assets/vendor

tasks:

  -
    name: Yarn Install
    plugin: exec
    environments:
      - staging
      - prod
    executable: yarn
    arguments: install

  -
    name: Tailwind CSS
    plugin: exec
    executable: npx
    arguments: tailwind build $RESOURCES + /css/styles.css -o $ASSETS + /output.css

  -
    name: Create folders
    plugin: create
    paths:
      - $ASSETS?ignore
      - $BUILD?ignore

  -
    name: Empty folders
    plugin: empty
    paths:
      - $ASSETS?ignore
      - $BUILD?ignore

  - 
    name: Copy assets
    plugin: copy
    paths:
      $NODE + /jquery/dist/jquery.js: $ASSETS + /vendor/jquery.js
      $NODE + /components-jqueryui/jquery-ui.js: $ASSETS + /vendor/jquery-ui.js
      $NODE + /components-jqueryui/themes/smoothness/**: $ASSETS + /vendor/jquery-ui/themes/smoothness/
      $NODE + /font-awesome/css/all.css: $ASSETS + /vendor/fontawesome.css
      $NODE + /font-awesome/webfonts/**: $ASSETS + /webfonts/
      $RESOURCES + /js/test.js: $ASSETS + /js/test.js
      $IMAGES+/**?extensions=png,gif: $ASSETS+/images/
      $VIEWS + /**?extensions=css,js: $ASSETS?remove_extension_folder

  -
    name: Replace text
    plugin: replace_text
    src: $ASSETS + /js/test.js
    find: This is some text to replace
    replace: Text has been replaced
    extensions:
      - js

  -
    name: Create autoinit
    plugin: combine
    output: $ASSETS + /vendor/autoinit.js
    paths:
      - $VENDOR + /hnhdigital-os/laravel-frontend-asset-loader/js/**
      - $VENDOR + /hnhdigital-os/laravel-frontend-assets/js/**

  -
    name: Process sass
    plugin: sass
    src: $RESOURCES + /sass/app.scss
    dest: $ASSETS + /app.css
    import-paths:
      - $NODE
    source-map: 
      path: $ASSETS + /app.map

  - 
    name: Revision assets
    plugin: revision
    cache: true
    minify: true
    src: $ASSETS
    dest: $BUILD
    manifest:
      formats:
        json: $BUILD + /rev-manifest.json
        php: config/rev-manifest.php


```

## Contributing

Please see [CONTRIBUTING](https://github.com/hnhdigital-os/fs-tasker/blob/master/CONTRIBUTING.md) for details.

## Credits

* [Rocco Howard](https://github.com/RoccoHoward)
* [All Contributors](https://github.com/hnhdigital-os/fs-tasker/contributors)

## License

The MIT License (MIT). Please see [License File](https://github.com/hnhdigital-os/fs-tasker/blob/master/LICENSE.md) for more information.
