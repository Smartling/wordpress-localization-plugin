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
                    sh "docker build --no-cache --rm --pull --tag=\"wordpress-localization-plugin-php74\" -f \"Buildplan/Dockerfile-Jenkins\" --build-arg ACFPRO_KEY=\"${ACF_PRO_KEY}\" --build-arg WP_VERSION=\"latest\" --build-arg GITHUB_OAUTH_TOKEN=\"${GITHUB_OAUTH}\" ."
                }
            }
        }

        stage('Release version check') {
            agent {
                label 'master'
            }

            steps {
                catchError(buildResult: 'UNSTABLE', stageResult: 'FAILURE') {
                    sh 'docker run --rm -w /plugin-dir -v $PWD:/plugin-dir wordpress-localization-plugin-php74:latest /releaseVersionCheck.sh'
                }
            }
        }

        stage('Run tests') {
            agent {
                label 'master'
            }

            steps {
                catchError(buildResult: 'FAILURE', stageResult: 'FAILURE') {
                    withCredentials([string(credentialsId: 'PROJECT_ID', variable: 'PROJECT_ID'), string(credentialsId: 'USER_IDENTIFIER', variable: 'USER_IDENTIFIER'), string(credentialsId: 'TOKEN_SECRET', variable: 'TOKEN_SECRET')]) {
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
                archiveArtifacts artifacts: '**/logfile-*'
            }
        }

        stage('Release on WordPress.org?') {
            when {
                anyOf {
                    expression { currentBuild.result == null }
                    expression { currentBuild.result == 'SUCCESS' }
                    expression { currentBuild.result == 'UNSTABLE' }
                }
            }
            agent none
            steps {
                timeout(time: 1, unit: 'HOURS') {
                    input "Release on WordPress.org?"
                }
            }
        }

        stage('WordPress.org SVN') {
            when {
                anyOf {
                    expression { currentBuild.result == null }
                    expression { currentBuild.result == 'SUCCESS' }
                    expression { currentBuild.result == 'UNSTABLE' }
                }
            }
            agent {
                label 'master'
            }

            steps {
                withCredentials([string(credentialsId: 'WORDPRESS_ORG_SVN_PASSWORD', variable: 'WORDPRESS_ORG_SVN_PASSWORD')]) {
                    sh 'docker run --rm -w /plugin-dir -v $PWD:/plugin-dir -e WORDPRESS_ORG_SVN_PASSWORD=$WORDPRESS_ORG_SVN_PASSWORD wordpress-localization-plugin-php74:latest /release.sh'
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
