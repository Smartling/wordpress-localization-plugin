pipeline {
    agent none

    options {
        buildDiscarder(logRotator(artifactDaysToKeepStr: '7', artifactNumToKeepStr: '10', daysToKeepStr: '7', numToKeepStr: '10'))
    }

    stages {
        stage('Scope dependencies') {
            agent {
                label 'master'
            }

            steps {
                sh '''
                composer global install ilab/namespacer
                ~/composer/vendor/ilab/namespacer/bin/namespacer --composer ./composer.json --package smartling-connector --namespace "Smartling\Vendor" inc
                # Second pass required
                ~/composer/vendor/ilab/namespacer/bin/namespacer --composer ./composer.json --package smartling-connector --namespace "Smartling\Vendor" inc
                find ./inc/lib -type f -name '*.php' -exec sed -i '' 's~Smartling\\Vendor\\Smartling\\Vendor\\~Smartling\\Vendor\\~g' {} +
                '''
            }
        }

        stage('Run tests') {
            agent {
                label 'master'
            }
            steps {
                dir('.') {
                    sh 'docker build --rm --tag="wordpress-localization-plugin-php74-wp55" -f "Buildplan/Dockerfile" --build-arg WP_VERSION="5.5.0" --build-arg ACF_PRO_VERSION="latest"'
                    sh 'docker run --rm -w /plugin-dir -v $PWD:/plugin-dir -e MYSQL_HOST=localhost -e CRE_PROJECT_ID=$PROJECT_ID -e CRE_USER_IDENTIFIER=$USER_IDENTIFIER -e CRE_TOKEN_SECRET="$TOKEN_SECRET" wordpress-localization-plugin-php74-wp55:latest'
                }
            }
        }

        stage('Publish archive') {
            agent {
                label 'master'
            }

            steps {
                archiveArtifacts artifacts: 'wordpress-connector.zip', onlyIfSuccessful: true
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
