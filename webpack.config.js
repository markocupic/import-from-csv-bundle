const Encore = require('@symfony/webpack-encore');

Encore
    .setOutputPath('public/')
    .setPublicPath('/bundles/markocupicimportfromcsv')
    .setManifestKeyPrefix('')

    //.addEntry('backend', './assets/backend.js') // Register Stimulus controllers

    .copyFiles({
      from: './node_modules/vue/dist',
      to: 'js/vue/dist/[path][name].[hash:8].[ext]',
      pattern: /(vue\.global\.prod\.js)$/,
    })
    .copyFiles({
      from: './assets/images',
      to: 'images/[path][name].[hash:8].[ext]',
    })
    .copyFiles({
      from: './assets/js',
      to: 'js/[path][name].[hash:8].[ext]',
    })

    // Typescripts
    //.addEntry('js/avatar_uploader', './assets/ts/avatar_uploader.ts')
    //.enableTypeScriptLoader()

    .disableSingleRuntimeChunk()
    .cleanupOutputBeforeBuild()
    .enableSourceMaps()
    .enableVersioning()

    .enablePostCssLoader()
    // Preprocessing scss to css
    .enableSassLoader()
    .enablePostCssLoader()
    .addStyleEntry('css/import_from_csv_app', './assets/styles/import_from_csv_app.scss')
    .addStyleEntry('css/loader', './assets/styles/loader.scss')

    // enables @babel/preset-env polyfills
    .configureBabelPresetEnv((config) => {
      config.useBuiltIns = 'usage';
      config.corejs = 3;
    })

    .enablePostCssLoader()
;

module.exports = Encore.getWebpackConfig();
