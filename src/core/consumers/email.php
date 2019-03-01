<?php
/**
 * @package    Techjoomla.Libraries
 * @author     Techjoomla <extensions@techjoomla.com>
 * @copyright  Copyright (c) 2009-2019 TechJoomla. All rights reserved.
 * @license    GNU General Public License version 2 or later.
 */

// No direct access
defined('_JEXEC') or die;

/**
 * TjQueue
 *
 * @package     Techjoomla.Libraries
 * @subpackage  Tjqueue
 * @since       1.0
 */
class TjqueueCoreEmail
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

		// Send email
		try
		{
			$recipients = $content->recipients;

			if (isset($recipients))
			{
				// Invoke JMail Class
				$mailer = \JFactory::getMailer();

				// Set cc for email
				if (isset($content->cc))
				{
					$mailer->addCC($content->cc);
				}

				// Set bcc for email
				if (isset($content->bcc))
				{
					$mailer->addBcc($content->bcc);
				}

				// Set bcc for email
				if (isset($content->replyTo))
				{
					$mailer->addReplyTo($content->replyTo);
				}

				if (isset($content->attachment))
				{
					if (isset($content->attachmentName))
					{
						$mailer->addAttachment($content->attachment, $content->attachmentName);
					}
					else
					{
						$mailer->addAttachment($content->attachment);
					}
				}

				// If you would like to send String Attachment in email
				if (isset($content->stringAttachment))
				{
					$stringAttachment = array();
					$stringAttachment = $content->stringAttachment;
					$encoding         = isset($stringAttachment['encoding']) ? $stringAttachment['encoding'] : '';
					$type             = isset($stringAttachment['type']) ? $stringAttachment['type'] : '';

					if (isset($stringAttachment['content']) && isset($stringAttachment['name']))
					{
						$mailer->addStringAttachment(
										$stringAttachment['content'],
										$stringAttachment['name'],
										$encoding,
										$type
									);
					}
				}

				// If you would like to send as HTML, include this line; otherwise, leave it out
				if ($content->isNotHTML != 1)
				{
					$mailer->isHTML();
				}

				// Set sender array so that my name will show up neatly in your inbox
				$mailer->setSender($content->from);

				// Add a recipient -- this can be a single address (string) or an array of addresses
				$mailer->addRecipient($recipients);

				// Set subject for email
				$mailer->setSubject($content->subject);

				// Set body for email
				$mailer->setBody($content->body);

				if ($content->email_status == 1)
				{
					$status = $mailer->send();

					if ($status)
					{
						$return['success'] = 1;
						$return['message'] = 'Email Sent successfully';
					}
					else
					{
						$return['success'] = 0;
						$return['message'] = 'Failed to send email';
					}

					return $return;
				}
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
