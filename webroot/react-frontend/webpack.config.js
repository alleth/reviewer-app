const path = require('path');
const HtmlWebpackPlugin = require('html-webpack-plugin');
const CopyWebpackPlugin = require('copy-webpack-plugin');
const webpack = require('webpack');

module.exports = (env, argv) => {
    const apiUrl = process.env.REACT_APP_API_URL || 'http://localhost/reviewer_app';
    const googleClientId = process.env.REACT_APP_GOOGLE_CLIENT_ID || '';

    return {
        entry: './src/index.js',
        output: {
            path: path.resolve(__dirname, 'dist'),
            // Content-hashed so every build gets a unique URL — a bare `main.js`
            // was being served stale by browser/ISP/CDN caches after a deploy.
            // HtmlWebpackPlugin injects the right <script> into index.html.
            filename: argv.mode === 'production' ? 'main.[contenthash].js' : 'main.js',
            publicPath: '/',
            clean: true,
        },
        mode: argv.mode || 'development',
        resolve: {
            extensions: ['.js', '.jsx'],
        },
        module: {
            rules: [
                {
                    test: /\.css$/i,
                    use: ['style-loader', 'css-loader', 'postcss-loader'],
                },
                {
                    test: /\.jsx?$/,
                    exclude: /node_modules/,
                    use: { loader: 'babel-loader' },
                },
                {
                    test: /\.(png|svg|jpg|jpeg|gif|ico)$/i,
                    type: 'asset/resource',
                },
            ],
        },
        plugins: [
            new HtmlWebpackPlugin({
                template: './public/index.html',
            }),
            // public/index.html is handled above as the HTML template; everything else in
            // public/ (favicons, manifest.json, robots.txt, ...) needs to be copied through
            // as-is, same as react-scripts' dev server already does implicitly.
            new CopyWebpackPlugin({
                patterns: [
                    {
                        from: 'public',
                        to: '.',
                        globOptions: { ignore: ['**/index.html'] },
                    },
                ],
            }),
            new webpack.DefinePlugin({
                'process.env.REACT_APP_API_URL': JSON.stringify(apiUrl),
                'process.env.REACT_APP_GOOGLE_CLIENT_ID': JSON.stringify(googleClientId),
            }),
        ],
    };
};
