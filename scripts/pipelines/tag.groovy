//!/usr/bin/env groovy

pipeline {
    agent any
    stages {
        stage('Cleanup') {
            steps {
                script {
                    // Cleanup previous stuff
                    sh("rm -rf jlike-scm")
                    sh("rm -rf builds")

                    // Cleanup jlike git folder, files
                    sh("rm -rf jlike")
                    sh("rm -rf .git")
                    sh("rm -rf .gitlab/merge_request_templates")
                    sh("rm -rf build")
                    sh("rm -rf com_jlike")
                    sh("rm -rf com_jomlike")
                    sh("rm -rf scripts")
                    sh("rm -f .gitignore")

                    // Cleanup common code repos
                    // sh("rm -rf common-code")
                    sh("rm -rf plg_system_tjassetsloader")
                    sh("rm -rf plg_system_tjupdates")
                    sh("rm -rf techjoomla-strapper")
                    sh("rm -rf lib_techjoomla")

                    // Make directories needed to generate build
                    sh("mkdir builds")
                    sh("mkdir jlike-scm")
                }
            }
        }

        stage('Checkout') {
            steps {
                script {
                    // This is important, we need clone into different folder here,
                    // Because, as part of tag based pull, we will be cloning same repo again
                    dir('jlike-scm') {
                        checkout scm
                    }
                }
            }
        }

        stage('Init') {
            steps {
                script {
                    // Get tag name
                    def  jlikeVersion = env.getProperty("jlikeVersion")
                    echo jlikeVersion

                    // Define subextensions array having unique git repos
                    // @TODO - move this to json itself?
                    def subextensions = ['jlike', 'plg_system_tjassetsloader', 'plg_system_tjupdates', 'techjoomla-strapper', 'lib_techjoomla']

                    if (jlikeVersion <= '2.1.2')
                    {
                        subextensions = ['jlike', 'plg_system_tjassetsloader', 'techjoomla-strapper', 'lib_techjoomla']
                    }

                    // def props = readJSON text: '{}' // Add JSON here or specify file below
                    def props = readJSON file: 'jlike-scm/build/version.json'

                    subextensions.eachWithIndex { item, index ->
                       // Do clone all subextensions repos by checking out corresponding release branch
                        sh("git clone --branch " + "v" + props['jlike'][jlikeVersion][item]['version'] + " --depth 1 " + props['jlike'][jlikeVersion][item]['repoUrl'])

                    }
                }
            }
        }

        stage('Copy files') {
            steps {
                script {
                    // Copy jlike from jlike repo into builds folder
                    sh("cp -r jlike/com_jomlike builds/")

                    // Copy plugins from common-code into com_jlike structure
                    sh("cp -r plg_system_tjassetsloader/src/tjassetsloader builds/com_jomlike/plugins/system/")
                    sh("cp -r plg_system_tjupdates/src/tjupdates           builds/com_jomlike/plugins/system/")

                    sh("mkdir builds/com_jomlike/strapper")
                    
                    // Copy strapper from techjoomla-strapper into com_jlike strucutre
                    sh("cp -r techjoomla-strapper/tj_strapper/* builds/com_jomlike/strapper/")

                    sh("cp -r lib_techjoomla/src/* builds/com_jomlike/libraries/")
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
                    // So cd into `jlike` dir
                    dir('jlike') {
                        // gitCommit   = env.getProperty('GIT_COMMIT')
                        gitCommit      = sh(returnStdout: true, script: 'git rev-parse HEAD').trim().take(8)
                        shortGitCommit = gitCommit[0..7]
                        echo gitCommit
                        echo shortGitCommit
                    }

                    // Now we are good to create zip for component
                    dir('builds/com_jomlike') {
                        sh('zip -rq ../com_jlike_v' + jlikeVersion + '_' + shortGitCommit + '.zip .')
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
                    // So cd into `jlike` dir
                    dir('jlike') {
                        // gitCommit   = env.getProperty('GIT_COMMIT')
                        gitCommit      = sh(returnStdout: true, script: 'git rev-parse HEAD').trim().take(8)
                        shortGitCommit = gitCommit[0..7]
                        echo gitCommit
                        echo shortGitCommit
                    }

                    // Change to builds directory
                    dir('builds') {
                        // Archive Artifact
                        archiveArtifacts 'com_jlike_v' + jlikeVersion + '_' + shortGitCommit + '.zip'
                    }
                }
            }
        }

        stage('Cleanup folders') {
            steps {
                script {
                    // Cleanup, so next time we get fresh files
                    sh("rm -r builds/com_jomlike/")
                }
            }
        }
    }
}
