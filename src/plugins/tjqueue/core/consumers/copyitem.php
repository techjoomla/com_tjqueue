<?php
/**
 * @package    Techjoomla.Libraries
 * @author     Techjoomla <extensions@techjoomla.com>
 * @copyright  Copyright (c) 2009-2019 TechJoomla. All rights reserved.
 * @license    GNU General Public License version 2 or later.
 */

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

// Instantiate the application.
$app = Factory::getApplication('site');
$app->initialise();

$lang = Factory::getLanguage();
$lang->load('plg_tjqueue_core', JPATH_ADMINISTRATOR, 'en-GB', true);

/**
 * TjQueue
 *
 * @package     Techjoomla.Libraries
 * @subpackage  Tjqueue
 * @since       __DEPLOY_VERSION__
 */
class TjqueueCoreCopyitem
{
	/**
	 * Plugin method with the same name as the event will be called automatically.
	 *
	 * @param   string  $message  A Message
	 *
	 * @return  array.
	 *
	 * @since 0.0.1
	 */
	public function consume($message)
	{
		$content = json_decode($message->getBody());
		
		$sourceClient = $content->sourceClient;
		$targetClient = $content->targetClient;
		$userId       = $content->userId;

		if (!$targetClient)
		{
			$targetClient = $sourceClient;
		}

		JLoader::import('components.com_tjfields.helpers.tjfields', JPATH_SITE);
		$tjFieldsHelper = new TjfieldsHelper;

		BaseDatabaseModel::addIncludePath(JPATH_SITE . '/components/com_tjucm/models');
		$model = BaseDatabaseModel::getInstance('Itemform', 'TjucmModel');
		$model->setClient($targetClient);

		$ucmOldData = array();
		$ucmOldData['clientComponent'] = 'com_tjucm';
		$ucmOldData['content_id'] = $content->ucmId;
		$ucmOldData['layout'] = 'edit';
		$ucmOldData['client']     = $sourceClient;
		$fileFieldArray = array();

		// Get the field values
		$extraFieldsData = $model->loadFormDataExtra($ucmOldData);

		// Code to replace source field name with destination field name
		foreach ($extraFieldsData as $fieldKey => $fieldValue)
		{
			$prefixSourceClient = str_replace(".", "_", $sourceClient);
			$fieldName = explode($prefixSourceClient . "_", $fieldKey);
			$prefixTargetClient = str_replace(".", "_", $targetClient);
			$targetFieldName = $prefixTargetClient . '_' . $fieldName[1];
			$tjFieldsTable = $tjFieldsHelper->getFieldData($targetFieldName);
			$fieldId = $tjFieldsTable->id;
			$fieldType = $tjFieldsTable->type;
			$fielParams = json_decode($tjFieldsTable->params);
			$sourceTjFieldsTable = $tjFieldsHelper->getFieldData($fieldKey);
			$sourceFieldParams = json_decode($sourceTjFieldsTable->params);
			$subFormData = array();

			if ($tjFieldsTable->type == 'ucmsubform' || $tjFieldsTable->type == 'subform')
			{
				$params = json_decode($tjFieldsTable->params)->formsource;
				$subFormClient = explode('components/com_tjucm/models/forms/', $params);
				$subFormClient = explode('form_extra.xml', $subFormClient[1]);
				$subFormClient = 'com_tjucm.' . $subFormClient[0];

				$params = $sourceFieldParams->formsource;
				$subFormSourceClient = explode('components/com_tjucm/models/forms/', $params);
				$subFormSourceClient = explode('form_extra.xml', $subFormSourceClient[1]);
				$subFormSourceClient = 'com_tjucm.' . $subFormSourceClient[0];

				$subFormData = (array) json_decode($fieldValue);
			}

			if ($fieldType == 'file')
			{
				$fileData = array();
				$fileData['field_id'] = $fieldId;
				$fileData['value'] = $fieldValue;
				$fileData['params'] = $fielParams;
				$fileData['sourceparams'] = $sourceFieldParams;
				$fileFieldArray[] = $fileData;
			}

			if ($subFormData)
			{
				foreach ($subFormData as $keyData => $data)
				{
					$prefixSourceClient = str_replace(".", "_", $sourceClient);
					$fieldName = explode($prefixSourceClient . "_", $keyData);
					$prefixTargetClient = str_replace(".", "_", $targetClient);
					$subTargetFieldName = $prefixTargetClient . '_' . $fieldName[1];
					$data = (array) $data;

					foreach ((array) $data as $key => $d)
					{
						$prefixSourceClient = str_replace(".", "_", $subFormSourceClient);
						$fieldName = explode($prefixSourceClient . "_", $key);
						$prefixTargetClient = str_replace(".", "_", $subFormClient);
						$subFieldName = $prefixTargetClient . '_' . $fieldName[1];

						JTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_tjfields/tables');
						$fieldTable = JTable::getInstance('field', 'TjfieldsTable');

						$fieldTable->load(array('name' => $key));

						if ($fieldName[1] == 'contentid')
						{
							$d = '';
						}

						$temp = array();
						unset($data[$key]);

						if (is_array($d))
						{
							// TODO Temprary used switch case need to modify code
							switch ($fieldTable->type)
							{
								case 'tjlist':
								case 'related':
								case 'multi_select':
									foreach ($d as $option)
									{
										$temp[] = $option->value;
									}

									if (!empty($temp))
									{
										$data[$subFieldName] = $temp;
									}

								break;

								default:
									foreach ($d as $option)
									{
										$data[$subFieldName] = $option->value;
									}
								break;
							}
						}
						else
						{
							$data[$subFieldName] = $d;
						}
					}

					unset($subFormData[$keyData]);
					$subFormData[$subTargetFieldName] = $data;
				}

				unset($extraFieldsData[$fieldKey]);
				$extraFieldsData[$targetFieldName] = $subFormData;
			}
			else
			{
				unset($extraFieldsData[$fieldKey]);
				$extraFieldsData[$targetFieldName] = $fieldValue;
			}
		}

		$ucmData = array();
		$ucmData['id'] 			= 0;
		$ucmData['client'] 		= $targetClient;
		$ucmData['parent_id'] 	= 0;
		$ucmData['state']		= 0;
		$ucmData['draft']	 	= 1;
		$ucmData['created_by']	= $userId;

		if ($content->clusterId)
		{
			$ucmData['cluster_id']	 	= $content->clusterId;
		}

		// Save data into UCM data table
		$result = $model->save($ucmData);
		$recordId = $model->getState($model->getName() . '.id');

		if ($recordId)
		{
			$formData = array();
			$formData['content_id'] = $recordId;
			$formData['fieldsvalue'] = $extraFieldsData;
			$formData['client'] = $targetClient;
			$formData['user_id'] = $userId;

			// If data is valid then save the data into DB
			$response = $model->saveExtraFields($formData);

			foreach ($fileFieldArray as $fileField)
			{
				$fileFieldValue = round(microtime(true)) . "_" . JUserHelper::genRandomPassword(5) . "_" . $fileField['value'];

				if (copy($fileField['sourceparams']->uploadpath . $fileField['value'], $fileField['params']->uploadpath . $fileFieldValue))
				{
					JTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_tjfields/tables');
					$fielValuedTable = JTable::getInstance('fieldsvalue', 'TjfieldsTable');
					$fielValuedTable->field_id = $fileField['field_id'];
					$fielValuedTable->content_id = $recordId;
					$fielValuedTable->value = $fileFieldValue;
					$fielValuedTable->user_id = $userId;;
					$fielValuedTable->client = $targetClient;
					$fielValuedTable->store();
				}
			}

			$msg = ($response) ? Text::_("COM_TJUCM_ITEM_COPY_SUCCESSFULLY") : Text::_("COM_TJUCM_FORM_SAVE_FAILED");
		}
		// Send email
		try
		{
			$recipients = Factory::getUser($userId)->email;

			if (isset($recipients))
			{
				// Invoke JMail Class
				$mailer = \JFactory::getMailer();

				// Set sender array so that my name will show up neatly in your inbox
				$config = Factory::getConfig();
				$ccMail = $config->get('mailfrom');
				$mailer->setSender($ccMail);

				// Add a recipient -- this can be a single address (string) or an array of addresses
				$mailer->addRecipient($recipients);

				// Set subject for email
				$mailer->setSubject(Text::_('PLG_TJQUEUE_CORE_EMAIL_SUBJECT'));

				// Set body for email
				$mailer->setBody(Text::_('PLG_TJQUEUE_CORE_EMAIL_BODY'));

				return $status = $mailer->send();
			}
			else
			{
				throw new Exception('Failed to send email. At least recipient should be available to send email');
			}
		}
		catch (Exception $e)
		{
			$return['success'] = 0;
			$return['message'] = $e->getMessage();

			return $return;
		}
	}
}
