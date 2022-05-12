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

        stage('Release version check') {
            agent {
                label 'master'
            }

            steps {
                dir('.') {
                    sh 'docker run --rm -w /plugin-dir -v $PWD:/plugin-dir wordpress-localization-plugin-php74:latest /releaseVersionCheck.sh'
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
                    dir('.') {
                        sh 'docker run --rm -w /plugin-dir -v $PWD:/plugin-dir -e WORDPRESS_ORG_SVN_PASSWORD=$WORDPRESS_ORG_SVN_PASSWORD wordpress-localization-plugin-php74:latest /release.sh'
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
