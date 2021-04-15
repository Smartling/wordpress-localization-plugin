# Notes for developers
## Testing
There is a Dockerfile that can be used to create an image to run unit and integration tests.
### Build

`docker build --rm --tag="wordpress-localization-plugin" -f "Buildplan/Dockerfile" --build-arg ACFPRO_KEY="" --build-arg WP_VERSION="latest" --build-arg ACF_PRO_VERSION="latest" --build-arg GITHUB_OAUTH_TOKEN="" .`

ACFPRO_KEY is required to register ACF Pro plugin

WP_VERSION is a specific version, e.g "4.5.2" or "nightly", as is ACF_PRO_VERSION

GITHUB_OAUTH_TOKEN is not strictly required, but without it you're extremely likely to run into issues when composer installs.

### Run

`docker run --rm -it -w /plugin-dir -v /path/to/wordpress-localization-plugin:/plugin-dir -e MYSQL_HOST="localhost" -e CRE_PROJECT_ID= -e CRE_USER_IDENTIFIER= -e CRE_TOKEN_SECRET= wordpress-localization-plugin:latest`

with CRE_PROJECT_ID, CRE_USER_IDENTIFIER and CRE_TOKEN_SECRET taken from Smartling dashboard.

Starting about 2020-06, there is a WordPress database error while executing tests, regarding a table 'wp.wp_2_yoast_indexable' that doesn't exist, but [yoast devs say it doesn't have any negative impact](https://wordpress.org/support/topic/wordpress-database-error-table-12/).
