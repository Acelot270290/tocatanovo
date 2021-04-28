const path = require('path');
const webpack = require('webpack');
const TerserPlugin = require('terser-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const {CleanWebpackPlugin} = require('clean-webpack-plugin');

module.exports = {
    mode: 'development',
    entry: {
        // SPB
        'admin-options-spb': [
            './src/admin-options/admin-options-spb/admin-options-spb.ts',
            './src/admin-options/admin-options-spb/admin-options-spb.scss',
        ],
        // Plus
        'admin-options-plus': [
            './src/admin-options/admin-options-plus/admin-options-plus.ts',
            './src/admin-options/admin-options-plus/admin-options-plus.scss',
        ],
        'frontend-shared': [
            './src/frontend/frontend-shared.ts',
        ],
        // Frontend Plus
        'frontend-plus': [
            './src/frontend/frontend-plus/frontend-plus.ts',
            './src/frontend/frontend-plus/frontend-plus.scss',
        ],
        // Frontend SPB
        'frontend-spb': [
            './src/frontend/frontend-spb/frontend-spb.ts',
            './src/frontend/frontend-spb/frontend-spb.scss',
        ],
        // Frontend Reference
        'frontend-reference-transaction': [
            './src/frontend/frontend-reference-transaction/frontend-reference-transaction.ts',
            './src/frontend/frontend-reference-transaction/frontend-reference-transaction.scss',
        ],
        // Frontend Shortcut
        'frontend-shortcut': [
            './src/frontend/frontend-shortcut/frontend-shortcut.ts',
            './src/frontend/frontend-shortcut/frontend-shortcut.scss',
        ],
    },
    output: {
        path: path.resolve(__dirname, './assets/dist/'),
        publicPath: '/dist/',
        filename: 'js/[name].js',
        chunkFilename: 'js/shared.js',
    },
    optimization: {
        splitChunks: {
            chunks: 'all',
        }
    },
    module: {
        rules: [
            {
                test: /\.(sass|scss)$/,
                loader: [
                    {
                        loader: MiniCssExtractPlugin.loader,
                        options: {
                            publicPath: '../',
                        }
                    },
                    'css-loader',
                    'postcss-loader',
                    'sass-loader'
                ]
            },
            {
                test: /\.(png|jpg|gif)$/,
                use: [{
                    loader: 'url-loader',
                    options: {
                        limit: 50,
                        outputPath: 'images',
                        name: '[name].[ext]',
                    }
                }]
            },
            {
                test: /\.tsx?$/,
                loader: 'ts-loader',
                exclude: /node_modules/,
                options: {
                    appendTsSuffixTo: [/\.vue$/],
                }
            },
        ]
    },
    plugins: [
        new MiniCssExtractPlugin({
            filename: 'css/[name].css',
            allChunks: true,
        }),
        new CleanWebpackPlugin(),
    ],
    resolve: {
        extensions: ['.ts', '.js', '.vue', '.json'],
        alias: {
            'vue$': 'vue/dist/vue.esm.js'
        }
    },
    devServer: {
        historyApiFallback: true,
        noInfo: true
    },
    performance: {
        hints: false
    },
    devtool: '#eval-source-map'
};

if (process.env.NODE_ENV === 'production') {
    module.exports.devtool = '#source-map';
    module.exports.plugins = (module.exports.plugins || []).concat([
        new webpack.DefinePlugin({
            'process.env': {
                NODE_ENV: '"production"'
            }
        }),
        new TerserPlugin(),
        new webpack.LoaderOptionsPlugin({
            minimize: true
        })
    ]);
}
