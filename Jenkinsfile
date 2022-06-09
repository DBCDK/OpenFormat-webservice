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
                        mkdir docker/www/src
                        cp -r src/* docker/www/src/
                    """
                }
                dir('docker/www') {
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
        stage('run soapui (integration) test'){
            agent {
                node { label 'devel9-head' }
            }
            environment {
                TESTURL = "http://openformat-php-develop.frontend-features.svc.cloud.dbc.dk/"
            }
            steps{
                script {
                    if (BRANCH_NAME == 'master') {
                        TESTURL = "http://openformat-php-master.frontend-staging.svc.cloud.dbc.dk/"
                    }
                }
                sleep(time:30,unit:"SECONDS")
                sh """
                set +e
                cd /opt/SmartBear/SoapUI-5.5.0/bin && ./testrunner.sh  -f "$WORKSPACE" -j -e $TESTURL $WORKSPACE/test/soapui/openformat-php-soapui-project.xml
                set -e
                """
                generateTestReport("*.xml")
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

/**
 * function to generate test-report
 * @param String pattern
 *  the pattern to look for (path to junit xml. eg. test-report.xml)
 */
void generateTestReport(String pattern, String type = "JUnit") {
    if (type == "JUnit") {
        step([$class        : 'XUnitBuilder',
              testTimeMargin: '3000',
              thresholdMode : 1,
              thresholds    : [failed(failureNewThreshold: '0',
                      failureThreshold: '0',
                      unstableNewThreshold: '0',
                      unstableThreshold: '0')],
              tools         : [JUnit(deleteOutputFiles: true,
                      failIfNotNew: true,
                      pattern: pattern,
                      skipNoTestFiles: false,
                      stopProcessingIfError: true)]])
    }
    if (type == "PHPUnit") {
        step([$class        : 'XUnitBuilder',
              testTimeMargin: '3000',
              thresholdMode : 1,
              thresholds    : [failed(failureNewThreshold: '0',
                      failureThreshold: '0',
                      unstableNewThreshold: '0',
                      unstableThreshold: '0')],
              tools         : [PHPUnit(deleteOutputFiles: true,
                      failIfNotNew: true,
                      pattern: pattern,
                      skipNoTestFiles: false,
                      stopProcessingIfError: true)]])
    }
}
