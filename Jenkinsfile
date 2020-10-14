#! groovy

pipeline {
    agent {
      node { label 'devel10-head' }
    }
    options {
        buildDiscarder(logRotator(artifactDaysToKeepStr: "", artifactNumToKeepStr: "", daysToKeepStr: "", numToKeepStr: "5"))
        timestamps()
        gitLabConnection('gitlab.dbc.dk')
        disableConcurrentBuilds()
    }
    environment {
        PRODUCT = 'openformat-php'
        BRANCH = BRANCH_NAME.replace("feature", "").replace("/", "").replace("_", "-").replace(".", "-")
        BUILDNAME = "Openformat-php :: ${BRANCH}"
        BUILD_ID = "${currentBuild.number}"
        DOCKER_REPO = "docker-dscrum.dbc.dk"
    }

    stages {
        stage('build code') {
            steps {
                dir('src') {
                    sh """
                        svn co https://svn.dbc.dk/repos/php/OpenLibrary/class_lib/trunk/ OLS_class_lib
                    """
                }
            }
        }
        stage('docker build') {
            steps {
                script {
                  sh """
                    cp -r src/* docker/www/src/
                  """
                }
                dir('docker/www') {
                    sh """
                      ls -al
                    """
                    script {
                        docker.build("${DOCKER_REPO}/${PRODUCT}-${BRANCH}:${BUILD_ID}")
                    }
                }
            }
        }
        stage('push to artifactory') {
            steps {
                script {
                    def artyServer = Artifactory.server 'arty'
                    def artyDocker = Artifactory.docker server: artyServer, host: env.DOCKER_HOST

                    def buildInfo = Artifactory.newBuildInfo()
                    buildInfo.name = BUILDNAME
                    buildInfo.env.capture = true
                    buildInfo.env.collect()
                    buildInfo = artyDocker.push("${DOCKER_REPO}/${PRODUCT}-${BRANCH}:${BUILD_ID}", 'docker-dscrum', buildInfo)

                    artyServer.publishBuildInfo buildInfo
                }
            }
        }
        stage('Deploy') {
          steps {
            script {
              if (BRANCH_NAME == 'master') {
                // Deploy to Kubernetes frontend-staging namespace.
                build job: 'Openformat/openformat-php/openformat-php-deploy/staging', parameters: [
                  string(name: 'BuildId', value: BUILD_ID),
                ]
              } else {
                // Deploy to Kubernetes frontend-features namespace.
                build job: 'Openformat/openformat-php/openformat-php-deploy/features', parameters: [
                  string(name: 'branch', value: BRANCH_NAME),
                  string(name: 'javascriptserver', value: 'develop'),
                ]
              }
            }
          }
        }
    }
    post {
      success {
        script {
          def BUILD = DOCKER_REPO + '/' + PRODUCT + '-' + BRANCH + ':' +  BUILD_ID.toString()
          echo BUILD
        }
      }
      failure {
        // @TODO do something meaningfull
        echo 'FAIL'
      }
      always {
        echo 'Clean up workspace.'
        sh "docker rmi ${DOCKER_REPO}/${PRODUCT}-${BRANCH}:${BUILD_ID}"
        deleteDir()
        cleanWs()
      }
    }
}
