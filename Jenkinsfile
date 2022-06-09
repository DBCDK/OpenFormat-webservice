#! groovy

def PRODUCT = "openformat-php"
def WORKER_NODE = "devel10"
def SOAPUI_NODE = "devel9"
def BRANCH = BRANCH_NAME.replace("feature", "").replace("/", "").replace("_", "-").replace(".", "-")
def NAMESPACE = (BRANCH == 'master') ? 'staging' : 'features'
def TESTURL
if (BRANCH_NAME == 'master') {
  TESTURL = "http://${PRODUCT}-master.frontend-${NAMESPACE}.svc.cloud.dbc.dk/"
} else {
  TESTURL = "http://${PRODUCT}-develop.frontend-${NAMESPACE}.svc.cloud.dbc.dk/"
}
def URL = 'http://' + PRODUCT  + '-' + BRANCH + '.' + "frontend-" + NAMESPACE + '.svc.cloud.dbc.dk' + '/'

// Docker setup
def DOCKER_REPO = 'docker-fbiscrum.artifacts.dbccloud.dk'
def DOCKER_IMAGENAME = "${DOCKER_REPO}/${PRODUCT}-${BRANCH.toLowerCase()}:${BUILD_NUMBER}"

//slack
def SLACK_CHANNEL_SUCCESS = "fe-jenkins"
def SLACK_CHANNEL_ERROR = "fe-fbi"

print "Parameter: PRODUCT = " + PRODUCT +
      "\n           BRANCH_NAME = " + BRANCH_NAME +
      "\n           DOCKER_REPO = " + DOCKER_REPO +
      "\n           DOCKER_IMAGENAME = " + DOCKER_IMAGENAME +
      "\n           NAMESPACE = " + NAMESPACE +
      "\n           URL = " + URL +
      "\n           TESTURL = " + TESTURL +
      "\n           BUILD_NUMBER = " + BUILD_NUMBER +
      "\n           WORKER_NODE = " + WORKER_NODE +
      "\n           SOAPUI_NODE = " + SOAPUI_NODE

pipeline {
  agent {
    node { label WORKER_NODE }
  }
  options {
    buildDiscarder(logRotator(artifactDaysToKeepStr: "", artifactNumToKeepStr: "", daysToKeepStr: "", numToKeepStr: "5"))
    timestamps()
    gitLabConnection('gitlab.dbc.dk')
    disableConcurrentBuilds()
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
            def image = docker.build(DOCKER_IMAGENAME)
          }
        }
      }
    }
    stage('push to artifactory') {
      steps {
        script {
          docker.image("${DOCKER_IMAGENAME}").push("${BUILD_NUMBER}")
          sh "docker rmi ${DOCKER_IMAGENAME}"
        }
      }
    }
    stage('Deploy') {
      steps {
        script {
          if (BRANCH_NAME == 'master') {
            // Deploy to Kubernetes frontend-staging namespace.
            build job: 'Openformat/openformat-php/openformat-php-deploy/staging', parameters: [
              string(name: 'BuildId', value: BUILD_NUMBER),
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
        node { label SOAPUI_NODE }
      }
      environment {
        TESTURL = "http://${PRODUCT}-develop.frontend-${NAMESPACE}.svc.cloud.dbc.dk/"
      }
      steps{
        script {
          if (BRANCH_NAME == 'master') {
            TESTURL = "http://${PRODUCT}-master.frontend-${NAMESPACE}.svc.cloud.dbc.dk/"
          }
        }
        sleep(time:30, unit:"SECONDS")
        sh """
          cd /opt/SmartBear/SoapUI-5.5.0/bin && ./testrunner.sh  -f "$WORKSPACE" -j -e $TESTURL $WORKSPACE/test/soapui/openformat-php-soapui-project.xml
        """
        // XUnitBuilder fails on devel9, so the test report is not build
        // generateTestReport("*.xml")
      }
    }
  }
  post {
    success {
      script {
        echo URL
        echo DOCKER_IMAGENAME
        slackSend(channel: SLACK_CHANNEL_SUCCESS,
          color: 'good',
          message: "${JOB_NAME} #${BUILD_NUMBER} completed, and pushed ${DOCKER_IMAGENAME} to artifactory.",
          tokenCredentialId: 'slack-global-integration-token')
      }
    }
    failure {
      script {
        echo 'FAIL'
        slackSend(channel: SLACK_CHANNEL_ERROR,
          color: 'warning',
          message: "${JOB_NAME} #${BUILD_NUMBER} failed and needs attention: ${BUILD_URL}",
            tokenCredentialId: 'slack-global-integration-token')
      }
    }
    always {
      echo 'Clean up workspace.'
      sh "docker rmi ${DOCKER_REPO}/${PRODUCT}-${BRANCH}:${BUILD_NUMBER}"
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
