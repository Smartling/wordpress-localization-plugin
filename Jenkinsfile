pipeline {
    agent none

    options {
        buildDiscarder(logRotator(artifactDaysToKeepStr: '7', artifactNumToKeepStr: '10', daysToKeepStr: '7', numToKeepStr: '10'))
    }

    stages {
        stage('Build image') {
            agent {
                label 'master'
            }

            steps {
                withCredentials([string(credentialsId: 'GITHUB_OAUTH', variable: 'GITHUB_OAUTH'), string(credentialsId: 'ACF_PRO_KEY', variable: 'ACF_PRO_KEY')]) {
                    sh "docker build --no-cache --rm --pull --tag=\"wordpress-localization-plugin-php74\" -f \"Buildplan/Dockerfile-Jenkins\" --build-arg ACFPRO_KEY=\"${ACF_PRO_KEY}\" --build-arg WP_VERSION=\"5.8.2\" --build-arg ACF_PRO_VERSION=\"5.11\" --build-arg GITHUB_OAUTH_TOKEN=\"${GITHUB_OAUTH}\" ."
                }
            }
        }

        stage('Run tests') {
            agent {
                label 'master'
            }

            steps {
                withCredentials([string(credentialsId: 'PROJECT_ID', variable: 'PROJECT_ID'), string(credentialsId: 'USER_IDENTIFIER', variable: 'USER_IDENTIFIER'), string(credentialsId: 'TOKEN_SECRET', variable: 'TOKEN_SECRET')]) {
                    dir('.') {
                        sh 'docker run --rm -w /plugin-dir -v $PWD:/plugin-dir -e MYSQL_HOST=localhost -e CRE_PROJECT_ID=$PROJECT_ID -e CRE_USER_IDENTIFIER=$USER_IDENTIFIER -e CRE_TOKEN_SECRET="$TOKEN_SECRET" wordpress-localization-plugin-php74:latest'
                    }
                }
            }
        }

        stage('Archive release') {
            agent {
                label 'master'
            }

            steps {
                archiveArtifacts artifacts: 'release.zip'
            }
        }

        stage('Release on WordPress.org?') {
            agent none
            steps {
                timeout(time: 1, unit: 'HOURS') {
                    input "Release on WordPress.org?"
                }
            }
        }

        stage('WordPress.org SVN') {
            agent {
                label 'master'
            }

            steps {
                withCredentials([string(credentialsId: 'WORDPRESS_ORG_SVN_PASSWORD', variable: 'WORDPRESS_ORG_SVN_PASSWORD')]) {
                    dir('./trunk') {
                        sh 'cp ../readme.txt ../smartling-connector.php .'
                        sh 'cp -r ../css ./css'
                        sh 'cp -r ../js ./js'
                        sh 'cp -r ../languages ./languages'
                        sh 'cp -r ../inc/config ./inc'
                        sh 'cp -r ../inc/Smartling ./inc'
                        sh 'svn --version'
                        sh "TAG=`grep '* Version' smartling-connector.php | sed 's/ \\* Version: *//'` && echo \$TAG && svn commit -m 'Update to v \$TAG' --username smartling --password $WORDPRESS_ORG_SVN_PASSWORD && svn copy https://plugins.svn.wordpress.org/smartling-connector/trunk https://plugins.svn.wordpress.org/smartling-connector/tags/\$TAG -m 'Tagging new version \$TAG'"
                        sh 'svn add --force * --auto-props --parents --depth infinity -q'
                    }
                }
            }
        }
    }

    post {
        always {
            node('master') {
                deleteDir()
            }
        }
    }
}
