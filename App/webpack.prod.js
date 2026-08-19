const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const { TanStackRouterWebpack } = require( '@tanstack/router-plugin/webpack' );
const path = require( 'path' );

const fs = require( 'fs' );

const assetsPath = fs.existsSync( path.resolve( __dirname, '../../../assets' ) )
  ? path.resolve( __dirname, '../../../assets' )
  : path.resolve( __dirname, '../assets' );

module.exports = {
  ...defaultConfig,
  target: 'web',
  externals: {
    ...( defaultConfig.externals || {}),
    react: 'React',
    'react-dom': 'ReactDOM'
  },
  output: {
    ...defaultConfig.output,
    filename: '[name].[contenthash].js',
    chunkFilename: '[name].[contenthash].js'
  },
  resolve: {
    ...defaultConfig.resolve,
    extensions: [ '.ts', '.tsx', '.js', '.jsx' ], // Add .ts and .tsx extensions
    alias: {
      '@': path.resolve( __dirname, 'src' ), // Add alias for src directory
      '@assets': assetsPath,
      'styled-components': path.resolve( __dirname, 'node_modules/styled-components' ) //fix style components error in console.
    }
  },
  module: {
    ...defaultConfig.module,
    rules: [
      ...defaultConfig.module.rules,

      // Add TypeScript loader
      {
        test: /\.[jt]sx?$/,
        exclude: /node_modules/,
        use: [
          {
            loader: 'ts-loader',
            options: {
              configFile: path.resolve( __dirname, 'tsconfig.json' )
            }
          }
        ]
      }
    ]
  },
  plugins: [
    ...defaultConfig.plugins,
    TanStackRouterWebpack({ target: 'react', autoCodeSplitting: true }) // Add TanStackRouterWebpack plugin
  ],
  optimization: {
    ...defaultConfig.optimization,
    minimize: true // Enable minification for production
  }
};
