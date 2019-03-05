//!/usr/bin/env groovy

// Get / Set release name
// TODO - remove hardcoded value
def  tjqueueVersion = '0.0.1' //env.getProperty("tjqueueVersion")
echo tjqueueVersion

pipeline {
    agent any
    stages {
        stage('Cleanup') {
            steps {
                script {
                    // Cleanup previous stuff
                    sh("rm -rf tjqueue-scm")
                    sh("rm -rf builds")

                    // Cleanup tjqueue git folder, files
                    sh("rm -rf com_tjqueue")
                    sh("rm -rf .git")
                    sh("rm -rf .gitlab/merge_request_templates")
                    sh("rm -rf build")
                    sh("rm -rf scripts")
                    sh("rm -f .gitignore")

                    // Make directories needed to generate build
                    sh("mkdir builds")
                    sh("mkdir tjqueue-scm")
                }
            }
        }

        stage('Checkout') {
            steps {
                script {
                    // This is important, we need clone into different folder here,
                    // Because, as part of tag based pull, we will be cloning same repo again
                    dir('tjqueue-scm') {
                        checkout scm
                    }
                }
            }
        }

        stage('Init') {
            steps {
                script {
                    // Define subextensions array having unique git repos
                    // @TODO - move this to json itself?
                    def subextensions = ['com_tjqueue']

                    // def props = readJSON text: '{}' // Add JSON here or specify file below
                    def props = readJSON file: 'tjqueue-scm/build/version.json'

                    subextensions.eachWithIndex { item, index ->
                       // Do clone all subextensions repos by checking out corresponding release branch
                       sh("git clone --branch " + props['tjqueue'][tjqueueVersion][item]['branch'] + " --depth 1 " + props['tjqueue'][tjqueueVersion][item]['repoUrl'])
                    }
                }
            }
        }

        stage('Copy files') {
            steps {
                script {
                    // Copy com_tjqueue from tjqueue repo into builds folder
                    sh("cp -r com_tjqueue/src/* builds/")
                }
            }
        }

        stage('Make zips') {
            steps {
                script {
                    // Get commit id
                    // @TODO - needs to define shortGitCommit at global level
                    def gitCommit      = ''
                    def shortGitCommit = ''

                    // For branch based build - we need the revision number of tag checked out,
                    // So cd into `com_tjqueue` dir
                    dir('com_tjqueue') {
                        // gitCommit   = env.getProperty('GIT_COMMIT')
                        gitCommit      = sh(returnStdout: true, script: 'git rev-parse HEAD').trim().take(8)
                        shortGitCommit = gitCommit[0..7]
                        echo gitCommit
                        echo shortGitCommit
                    }

                    // Now we are good to create zip for component
                    dir('builds/com_tjqueue') {
                        sh('zip -rq com_tjqueue.zip com_tjqueue')
                        sh('zip -rq core.zip core')
                        sh('zip -rq ../com_tjqueue_v' + tjqueueVersion + '_' + shortGitCommit + '.zip .')
                    }
                }
            }
        }

        stage('Archive') {
            steps {
                script {
                    // Get commit id
                    // @TODO - needs to define shortGitCommit at global level
                    def gitCommit      = ''
                    def shortGitCommit = ''

                    // For branch based build - we need the revision number of tag checked out,
                    // So cd into `com_tjqueue` dir
                    dir('com_tjqueue') {
                        // gitCommit   = env.getProperty('GIT_COMMIT')
                        gitCommit      = sh(returnStdout: true, script: 'git rev-parse HEAD').trim().take(8)
                        shortGitCommit = gitCommit[0..7]
                        echo gitCommit
                        echo shortGitCommit
                    }

                    // Change to builds directory
                    dir('builds') {
                        // Archive Artifact
                        archiveArtifacts 'com_tjqueue_v' + tjqueueVersion + '_' + shortGitCommit + '.zip'
                    }
                }
            }
        }

        stage('Cleanup folders') {
            steps {
                script {
                    // Cleanup, so next time we get fresh files
                    sh("rm -r builds/com_tjqueue/")
                }
            }
        }
    }
}
