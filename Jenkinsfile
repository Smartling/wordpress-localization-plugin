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
                    sh "docker build --no-cache --rm --pull --tag=\"wordpress-localization-plugin-php74\" -f \"Buildplan/Dockerfile-Jenkins\" --build-arg ACFPRO_KEY=\"${ACF_PRO_KEY}\" --build-arg WP_VERSION=\"latest\" --build-arg ACF_PRO_VERSION=\"${ACF_PRO_VERSION}\" --build-arg GITHUB_OAUTH_TOKEN=\"${GITHUB_OAUTH}\" ."
                }
            }
        }

        stage('Run tests') {
            agent {
                label 'master'
            }
            steps {
                dir('.') {
                    sh 'docker run --rm -w /plugin-dir -v $PWD:/plugin-dir -e MYSQL_HOST=localhost -e CRE_PROJECT_ID=$PROJECT_ID -e CRE_USER_IDENTIFIER=$USER_IDENTIFIER -e CRE_TOKEN_SECRET="$TOKEN_SECRET" wordpress-localization-plugin-php74:latest'
                }
            }
        }

        stage('Publish archive') {
            agent {
                label 'master'
            }

            steps {
                archiveArtifacts artifacts: 'wordpress-connector.zip'
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
