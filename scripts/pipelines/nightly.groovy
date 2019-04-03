//!/usr/bin/env groovy

// Get / Set release name
// TODO - remove hardcoded value
def  tjqueueVersion = '0.0.1' //env.getProperty("tjqueueVersion")
//echo tjqueueVersion

pipeline {
    agent any
    stages {
        stage('Cleanup') {
            steps {
                script {
                    // Cleanup previous stuff
                    def cleanUpList =["tjqueue-scm","builds","com_tjqueue",".git",".gitlab/merge_request_templates","build","scripts", ".gitignore","sqs","dbal"] as String[]
                    for (item in cleanUpList) {
                       sh("rm -rf "+item);
                    }

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
                    def subextensions = ['com_tjqueue','dbal','sqs']
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
                    sh("rsync -a dbal/ builds/com_tjqueue/admin/libraries/lib/")
                    sh("rsync -a sqs/ builds/com_tjqueue/admin/libraries/lib/")
                    sh("mv builds/com_tjqueue/admin/libraries/lib/composer_files/* builds/com_tjqueue/admin/libraries/lib/")
                    sh("rm -rf builds/com_tjqueue/admin/libraries/lib/composer_files")
                }
            }
        }

       stage('composer') {
           steps {
               script {
                   sh("cd builds/com_tjqueue/admin/libraries/lib/ && composer install --no-dev --ignore-platform-reqs")
                    def cleanUpList = [".git","tests","docs","Tests"] as String[]
                    def deleteCmd = '-exec rm -r "{}" +';
                    for (item in cleanUpList) {
                       sh("cd builds/com_tjqueue/admin/libraries/lib/ && find . -name "+item+" "+ deleteCmd);
                    }
                    
                    def awsCleanUp =["Acm","CostExplorer","IoT1ClickProjects","Rds","ACMPCA","IoTAnalytics","RDSDataService","AlexaForBusiness","Crypto","IotDataPlane","Redshift","Amplify","IoTJobsDataPlane","Rekognition","DatabaseMigrationService","Kafka","ResourceGroups","ApiGateway","DataPipeline","Kinesis","ResourceGroupsTaggingAPI","ApiGatewayManagementApi","DataSync","KinesisAnalytics","RoboMaker","ApiGatewayV2","DAX","KinesisAnalyticsV2","Route53","ApplicationAutoScaling","DeviceFarm","KinesisVideo","Route53Domains","ApplicationDiscoveryService","DirectConnect","KinesisVideoArchivedMedia","Route53Resolver","AppMesh","DirectoryService","KinesisVideoMedia","S3","Appstream","DLM","Kms","S3Control","AppSync","DocDB","Lambda","SageMaker","Athena","DynamoDb","LexModelBuildingService","SageMakerRuntime","AutoScaling","DynamoDbStreams","LexRuntimeService","SecretsManager","AutoScalingPlans","Ec2","LicenseManager","SecurityHub","Backup","Ecr","Lightsail","ServerlessApplicationRepository","Batch","Ecs","MachineLearning","ServiceCatalog","Budgets","Efs","Macie","ServiceDiscovery","Chime","EKS","MarketplaceCommerceAnalytics","Ses","ElastiCache","MarketplaceEntitlementService","Sfn","Cloud9","ElasticBeanstalk","MarketplaceMetering","Shield","CloudDirectory","ElasticLoadBalancing","MediaConnect","CloudFormation","ElasticLoadBalancingV2","MediaConvert","signer","CloudFront","ElasticsearchService","MediaLive","Sms","CloudHsm","ElasticTranscoder","MediaPackage","SnowBall","CloudHSMV2","Emr","MediaStore","Sns","CloudSearch","MediaStoreData","CloudSearchDomain","MediaTailor","Ssm","CloudTrail","Exception","MigrationHub","StorageGateway","CloudWatch","Firehose","Mobile","Sts","CloudWatchEvents","FMS","MQ","Support","CloudWatchLogs","FSx","MTurk","Swf","CodeBuild","GameLift","Multipart","Textract","CodeCommit","Glacier","Neptune","TranscribeService","CodeDeploy","GlobalAccelerator","OpsWorks","Transfer","CodePipeline","Glue","OpsWorksCM","Translate","CodeStar","Greengrass","Organizations","Waf","CognitoIdentity","GuardDuty","PI","WafRegional","CognitoIdentityProvider","Pinpoint","WorkDocs","CognitoSync","Health","PinpointEmail","WorkLink","Comprehend","Iam","PinpointSMSVoice","WorkMail","ComprehendMedical","ImportExport","Polly","WorkSpaces","ConfigService","Inspector","Pricing","XRay","Connect","Iot","QuickSight","CostandUsageReportService","IoT1ClickDevicesService","RAM"] as String[]
                    for (item in awsCleanUp) {
                       sh("cd builds/com_tjqueue/admin/libraries/lib/vendor/aws/aws-sdk-php/src && rm -rf "+item);
                    }
                    
                    def awsDataClenaup=["acm","cognito-sync","fsx","mediapackage","sagemaker","acm-pca","comprehend","gamelift","mediastore","secretsmanager","alexaforbusiness","comprehendmedical","glacier","mediastore-data","securityhub","amplify","config","globalaccelerator","mediatailor","serverlessrepo","apigateway","connect","glue","metering.marketplace","servicecatalog","apigatewaymanagementapi","cur","greengrass","mgh","servicediscovery","apigatewayv2","data.iot","guardduty","mobile","shield","application-autoscaling","datapipeline","health","monitoring","signer","appmesh","datasync","iam","mq","sms","appstream","dax","importexport","mturk-requester","sms-voice","appsync","devicefarm","inspector","neptune","snowball","athena","directconnect","iot","opsworks","sns","autoscaling","discovery","iot1click-devices","opsworkscm","autoscaling-plans","dlm","iot1click-projects","organizations","ssm","backup","dms","iotanalytics","pi","states","batch","docdb","iot-jobs-data","pinpoint","storagegateway","budgets","ds","kafka","pinpoint-email","streams.dynamodb","ce","dynamodb","kinesis","polly","sts","chime","ec2","kinesisanalytics","pricing","support","cloud9","ecr","kinesisanalyticsv2","quicksight","swf","clouddirectory","ecs","kinesisvideo","ram","textract","cloudformation","eks","kinesis-video-archived-media","rds","transcribe","cloudfront","elasticache","kinesis-video-media","rds-data","transfer","cloudhsm","elasticbeanstalk","kms","redshift","translate","cloudhsmv2","elasticfilesystem","lambda","rekognition","waf","cloudsearch","elasticloadbalancing","lex-models","resource-groups","waf-regional","cloudsearchdomain","elasticloadbalancingv2","license-manager","resourcegroupstaggingapi","workdocs","cloudtrail","elasticmapreduce","lightsail","robomaker","worklink","codebuild","elastictranscoder","logs","route53","workmail","codecommit","email","machinelearning","route53domains","workspaces","codedeploy","entitlement.marketplace","macie","route53resolver","xray","codepipeline","es","marketplacecommerceanalytics","runtime.lex","codestar","events","mediaconnect","runtime.sagemaker","cognito-identity","firehose","mediaconvert","s3","cognito-idp","fms","medialive","s3control"] as String[]
                    for (item in awsDataClenaup) {
                       sh("cd builds/com_tjqueue/admin/libraries/lib/vendor/aws/aws-sdk-php/src/data && rm -rf "+item);
                    }
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
                    dir('builds') {
                        sh('zip -rq com_tjqueue.zip com_tjqueue')
                        sh('rm -rf com_tjqueue')
                        
                        sh('zip -rq core.zip plugins/tjqueue/core')
                        //sh('rm -rf plugins/tjqueue/core')

                        sh('zip -rq cli.zip cli')
                        sh('rm -rf cli')

                        sh('zip -rq --exclude=com_tjqueue ../pkg_tjqueue_v' + tjqueueVersion + '_' + shortGitCommit + '.zip .')
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

                    archiveArtifacts 'pkg_tjqueue_v' + tjqueueVersion + '_' + shortGitCommit + '.zip'
                }
            }
        }

        stage('Cleanup folders') {
            steps {
                script {
                    // Cleanup, so next time we get fresh files
                    sh("rm -r builds")
                }
            }
        }
    }
}
