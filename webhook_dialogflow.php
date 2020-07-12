<?php

require_once './vendor/autoload.php';

use Kreait\Firebase\Factory;

$bugsnag = Bugsnag\Client::make('ddf9f727ba005c70798645f3355cd3e7');
Bugsnag\Handler::register($bugsnag);

$input = json_decode(file_get_contents('php://input'), true);


$messenger_bot = new DialogFlowWebhook($input);

class DialogFlowWebhook
{

	protected $sender_id;
	protected $intent;
	protected $text;

	function __construct($input)
	{
		$this->input = $input;

		$this->factory = (new Factory)->withServiceAccount('./firebase_credentials.json');
		$this->database = $this->factory->createDatabase();
		$this->users_db = $this->database->getReference('users');
		$this->messages_db = $this->database->getReference('messages');

		$this->saveMessage();
		$this->checkForProfile();
	}

	function checkForProfile()
	{

		if (strpos($this->intent, 'intent.profilo.creaProfilo') === 0) {
			$intent_pieces = explode('.', $this->intent);
			$field = end($intent_pieces);

			$this->setField($field, $this->text);
		}
	}


	function saveMessage()
	{

		$this->sender_id = $this->getSenderIdByApp($this->input['originalDetectIntentRequest']['source']);
		$this->intent = $this->input['queryResult']['intent']['displayName'];
		$this->text = $this->input['queryResult']['queryText'];
		$timestamp = round(microtime(true) * 1000);
		$last_message = $this->getLastMessage();
		$time_between_msgs = $timestamp - $last_message['timestamp'];

		$message_data = [
			'action' => $this->input['queryResult']['action'],
			'intent' => $this->intent,
			'text' => $this->text,
			'origin_app' => $this->input['originalDetectIntentRequest']['source'],
			'origin_user_id' => $this->sender_id,
			'timestamp' => $timestamp,
			'time_between_msgs' => $time_between_msgs
		];

		if ($this->intent === 'intent.profilo.mostraProfilo') {
			$this->sendProfile();
		}

		$this->messages_db->getChild($this->input['responseId'])->set($message_data);
	}

	function sendProfile()
	{

		$userInfo = $this->users_db->getChild($this->sender_id)->getValue();
		$msg = '';

		$msg .= 'Nome azienda: ' . $userInfo['company_name'] . "\n";
		$msg .= 'Estensione aziendale: ' . $userInfo['company_extension'] . "\n";
		$msg .= 'Specie colturale: ' . $userInfo['coltures_type'] . "\n";
		$msg .= 'Superficie colturale: ' . $userInfo['coltures_extension'] . "\n";
		$msg .= 'Sistema Irriguo: ' . $userInfo['sistema_irriguo'] . "\n";

		return json_encode([
			'fulfillmentText' => 'response',
			'fulfillmentMessages' => [
				[
					'text' => [
						'text' => [
							$msg
						]
					]
				]
			]
		]);
	}

	function getSenderIdByApp($app)
	{

		if ($app == 'telegram') {
			$sender_id = $this->input['originalDetectIntentRequest']['payload']['data']['from']['id'];
		} elseif ($app == 'facebook') {
			$sender_id = $this->input['originalDetectIntentRequest']['payload']['data']['sender']['id'];
		}

		return $sender_id;
	}

	function setField($field, $value)
	{

		$user_info = $this->users_db->getChild($this->sender_id)->getValue();

		$user_info[$field] = $value;

		$this->users_db->getChild($this->sender_id)->set($user_info);
	}

	function getField($field)
	{
		return $this->users_db->getChild($this->sender_id)->getChild($field)->getValue();
	}

	function getLastMessage()
	{
		$my_messages = $this->messages_db->orderByChild('origin_user_id')->equalTo($this->sender_id)->getSnapshot()->getValue();
		$last_message = array_reduce($my_messages, function ($a, $b) {
			return @$a['timestamp'] > $b['timestamp'] ? $a : $b;
		});

		return $last_message;
	}
}
