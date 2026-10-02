const path = require("path");

module.exports = {
  outputDir: "html",
  // 发布包不需要 source map（vue-cli 默认开启，会平白多出约 7MB 体积）
  productionSourceMap: false,
  // 构建期占位符：运行时由 PHP（sm-ms-management.php 的 display_page()）把它替换成
  // 插件真实的 URL 路径。原实现硬编码 "../wp-content/plugins/wp-editormd/..."，
  // 一旦插件目录被重命名或使用自定义插件目录，页面 JS/CSS 就会 404。
  //
  // 说明：iframe 文档位于 wp-admin/admin-ajax.php，而静态资源在插件目录，
  // 两者不在同一路径下，因此不能用 "./" 之类的相对路径，必须用绝对路径。
  publicPath: "/__EDITORMD_ASSET_BASE__/src/Pages/page/sm-ms-management/html/",
  configureWebpack:{
    optimization: {
      splitChunks: {
        minSize: 204800,
        maxSize: 409600,
      }
    },
    performance: {
      maxAssetSize:      process.env.NODE_ENV === "development" ? 1024 * 4096 : 1024 * 2048,
      maxEntrypointSize: process.env.NODE_ENV === "development" ? 1024 * 4096 : 1024 * 2048,
    },
    resolve: {
      extensions: ['.ts', '.js', '.vue', '.json'],
      alias: {
        'src': path.resolve(__dirname, 'src'),
      },
    }
  },
  css: {
    loaderOptions: {
      less: {
        javascriptEnabled: true
      }
    }
  },
  pluginOptions: {
    i18n: {
      locale: "en",
      fallbackLocale: "en",
      localeDir: "locales",
      enableInSFC: true
    },
  },
};
