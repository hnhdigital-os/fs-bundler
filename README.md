# FS-TASKER

Note: Currently WIP! 

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

* PHP 7.1.3 (min)

## Installation

Via commmand line:

`bash <(curl -s https://hnhdigital-os.github.io/fs-tasker/builds/install)`

Download the latest build:

`curl -o ./fs-tasker -LSs https://github.com/hnhdigital-os/fs-tasker/raw/master/builds/fs-tasker`

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
environments:
  - dev
  - staging
  - prod

options:

paths:
  PATH_SASS: resources/assets/sass
  PATH_NODE: node_modules
  PATH_PUBLIC_ASSETS: public/assets
  PATH_PUBLIC_BUILD: public/build
  PATH_VENDOR: vendor
  PATH_RESOURCES: resources
  PATH_RES_VIEWS: resources/views
  PATH_RES_ASSET_IMAGES: resources/assets/images
  PATH_RES_ASSET_VENDOR: resources/assets/vendor

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
    arguments: tailwind build PATH_RESOURCES + /css/styles.css -o PATH_PUBLIC_ASSETS + /output.css

  -
    name: Create folders
    plugin: create
    paths:
      - PATH_PUBLIC_ASSETS?ignore
      - PATH_PUBLIC_BUILD?ignore

  -
    name: Empty folders
    plugin: empty
    paths:
      - PATH_PUBLIC_ASSETS?ignore
      - PATH_PUBLIC_BUILD?ignore

  - 
    name: Copy assets
    plugin: copy
    paths:
      PATH_NODE + /jquery/dist/jquery.js: PATH_PUBLIC_ASSETS + /vendor/jquery.js
      PATH_NODE + /components-jqueryui/jquery-ui.js: PATH_PUBLIC_ASSETS + /vendor/jquery-ui.js
      PATH_NODE + /components-jqueryui/themes/smoothness/**: PATH_PUBLIC_ASSETS + /vendor/jquery-ui/themes/smoothness/
      PATH_NODE + /font-awesome/css/all.css: PATH_PUBLIC_ASSETS + /vendor/fontawesome.css
      PATH_NODE + /font-awesome/webfonts/**: PATH_PUBLIC_ASSETS + /webfonts/
      PATH_RESOURCES + /js/test.js: PATH_PUBLIC_ASSETS + /js/test.js
      PATH_RES_ASSET_IMAGES+/**?extensions=png,gif: PATH_PUBLIC_ASSETS+/images/
      PATH_RES_VIEWS + /**?extensions=css,js: PATH_PUBLIC_ASSETS?remove_extension_folder

  -
    name: Replace text
    plugin: replace_text
    src: PATH_PUBLIC_ASSETS + /js/test.js
    find: This is some text to replace
    replace: Text has been replaced
    extensions:
      - js

  -
    name: Create autoinit
    plugin: combine
    output: PATH_PUBLIC_ASSETS + /vendor/autoinit.js
    paths:
      - PATH_VENDOR + /hnhdigital-os/laravel-frontend-asset-loader/js/**
      - PATH_VENDOR + /hnhdigital-os/laravel-frontend-assets/js/**

  -
    name: Process sass
    plugin: sass
    src: PATH_RESOURCES + /sass/app.scss
    dest: PATH_PUBLIC_ASSETS + /app.css
    import-paths:
      - PATH_NODE
    source-map: 
      path: PATH_PUBLIC_ASSETS + /app.map

  - 
    name: Revision assets
    plugin: revision
    cache: true
    minify: true
    src: PATH_PUBLIC_ASSETS
    dest: PATH_PUBLIC_BUILD
    manifest:
      formats:
        json: PATH_PUBLIC_BUILD + /rev-manifest.js
        php: config/rev-manifest.php


```

## Contributing

Please see [CONTRIBUTING](https://github.com/hnhdigital-os/fs-tasker/blob/master/CONTRIBUTING.md) for details.

## Credits

* [Rocco Howard](https://github.com/RoccoHoward)
* [All Contributors](https://github.com/hnhdigital-os/fs-tasker/contributors)

## License

The MIT License (MIT). Please see [License File](https://github.com/hnhdigital-os/fs-tasker/blob/master/LICENSE.md) for more information.
