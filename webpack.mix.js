const mix = require('laravel-mix');
const path = require('path');
const webpack = require('webpack');
const { BundleAnalyzerPlugin } = require('webpack-bundle-analyzer');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */

mix.js('resources/js/app.js', 'public/js')
  .vue({ version: 2 })
  .sass('resources/sass/style.scss', 'public/css/app.css', {
    implementation: require('sass')
  })
  .sass('resources/sass/auth.scss', 'public/css/auth.css', {
    implementation: require('sass')
  })
  .extract([
    'vue',
    'vuex',
    'axios',
    'element-ui',
    'lodash',
    'moment',
    'jquery',
    'bootstrap',
    // FASE 1: Agregar vendors pesados para separar del bundle principal
    '@ckeditor/ckeditor5-build-classic',
    'chart.js',
    'socket.io-client',
    'vue-chartjs',
    'canvas',
    'jqwidgets-scripts'
  ])
  .version();

// Generate module aliases dynamically
const modules = [
  'Sale', 'Finance', 'Purchase', 'Expense', 'Order', 'Account',
  'Item', 'Hotel', 'DocumentaryProcedure', 'Payment', 'MercadoPago',
  'Suscription', 'MobileApp', 'LevelAccess', 'Report', 'Inventory',
  'MultiUser', 'QrChatBuho', 'QrApi'
];

const moduleAliases = modules.reduce((aliases, module) => {
  const moduleName = module === 'DocumentaryProcedure' ? 'Documentary' : module;
  const viewsPath = module === 'Inventory'
    ? `modules/${module}/Resources/assets/js`
    : `modules/${module}/Resources/assets/js/views`;

  aliases[`@viewsModule${moduleName}`] = path.resolve(__dirname, viewsPath);
  return aliases;
}, {});

// Webpack configuration
mix.webpackConfig({
  resolve: {
    alias: {
      'vue$': 'vue/dist/vue.esm.js',
      '@components': path.resolve(__dirname, 'resources/js/components'),
      '@views': path.resolve(__dirname, 'resources/js/views/tenant'),
      '@helpers': path.resolve(__dirname, 'resources/js/helpers'),
      '@mixins': path.resolve(__dirname, 'resources/js/mixins'),
      ...moduleAliases
    }
  },
  // FASE 1: Optimización de code splitting (COMPATIBLE con .extract())
  optimization: {
    moduleIds: 'deterministic',
    runtimeChunk: 'single',
    splitChunks: {
      chunks: 'async', // IMPORTANTE: Mantener 'async' para compatibilidad con .extract()
      maxAsyncRequests: 30,
      maxInitialRequests: 30,
      minSize: 20000,
      cacheGroups: {
        // Vendors de node_modules (async para no conflicto)
        defaultVendors: {
          test: /[\\/]node_modules[\\/]/,
          priority: -10,
          reuseExistingChunk: true,
        },
        // Módulos de la app por módulo (async)
        appModules: {
          test: /[\\/]modules[\\/]/,
          name(module) {
            const moduleMatch = module.context.match(/modules[\\/](.*?)[\\/]/);
            if (moduleMatch) {
              return `module-${moduleMatch[1].toLowerCase()}`;
            }
            return 'modules-common';
          },
          chunks: 'async', // async para no romper lazy loading
          priority: 20,
          minSize: 30000, // Solo modules > 30KB
          minChunks: 1,
          reuseExistingChunk: true
        },
        // Default group para código común
        default: {
          minChunks: 2,
          priority: -20,
          reuseExistingChunk: true
        }
      }
    }
  },
  // Cache del filesystem para builds más rápidos
  cache: {
    type: 'filesystem',
    buildDependencies: {
      config: [__filename]
    }
  },
  // Performance hints
  performance: {
    hints: false,
    maxEntrypointSize: 512000,
    maxAssetSize: 512000
  },
  // Plugins
  plugins: [
    // Bundle analyzer plugin
    new BundleAnalyzerPlugin({
      analyzerMode: process.env.ANALYZE ? 'server' : 'disabled',
      openAnalyzer: true,
      generateStatsFile: true,
      statsFilename: '../storage/app/stats.json'
    }),
    // FASE 1: Excluir locales de moment.js (ahorro ~400KB)
    new webpack.IgnorePlugin({
      resourceRegExp: /^\.\/locale$/,
      contextRegExp: /moment$/
    })
  ]
});

// Disable notifications
mix.disableSuccessNotifications();

// Production-specific optimizations
if (mix.inProduction()) {
  mix.sourceMaps(false);
} else {
  // Enable source maps only in development
  mix.sourceMaps(true, 'eval-source-map');
}
