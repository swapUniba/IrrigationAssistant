<?php

require_once './vendor/autoload.php';

use Kreait\Firebase\Factory;

$bugsnag = Bugsnag\Client::make('29e157bb9543b1d2fee4b8d5eb67f8c8');
Bugsnag\Handler::register($bugsnag);

$verify_token = 'Irrigation_Assistant';
$hub_verify_token = null;

// check token at setup
if ($_REQUEST['hub_verify_token'] === $verify_token) {
	echo $_REQUEST['hub_challenge'];
	exit;
}

$input = json_decode(file_get_contents('php://input'), true);
// debug(file_get_contents('php://input'));

$messenger_bot = new MessengerBot($input);

class MessengerBot
{

	protected $sender_id;
	protected $access_token = 'EAAJZABwnRpAEBABhKbXw8INaq7ZAzYHZA1tBA6XXPgKsfVHQHKGSEDnXrfKjOQ7gCBiGYM5Gp3bbgOecupAwTbK7nosP1NIYNPBKr980B2MjsDBiZA9tpLS7fonO7IWsAb18EkxmoKNx3ZAK0aPZBCdjCcQTcWwsJ562ofgKesrAZDZD';
	protected $message_payload;
	protected $message_id;

	function __construct($input)
	{
		$this->factory = (new Factory)->withServiceAccount('./firebase_credentials.json');
		$this->database = $this->factory->createDatabase();
		$this->users_db = $this->database->getReference('users');
		$this->messages_db = $this->database->getReference('messages');

		$this->sender_id = $input['entry'][0]['messaging'][0]['sender']['id'];
		$this->message_payload = $input['entry'][0]['messaging'][0];
		$this->message_id = md5(microtime(true));

		$this->debug(json_encode($input, JSON_PRETTY_PRINT));

		if (array_key_exists('message', $input['entry'][0]['messaging'][0])) {

			$messageText = $input['entry'][0]['messaging'][0]['message']['text'];
			$this->debug($messageText);

			$response = null;

			switch ($this->getStatus()) {

				case "awating_name":
					$this->setField("company_name", $messageText);
					$this->setStatus("awating_extension");
					$this->reply('Qual è la tua estensione aziendale in metri quadri?');
					break;

				case "awating_extension":
					$this->setField("company_extension", $messageText);
					$this->setStatus("awating_colture");
					$this->reply('Quali specie o che varietà colturali possiedi?');
					break;

				case "awating_colture":
					$this->setField("coltures_type", $messageText);
					$this->setStatus("awating_colture_extension");
					$this->reply('Qual è l\'estensione della tua superﬁcie colturale in metri quadri?');
					break;

				case "awating_colture_extension":
					$this->setField("coltures_extension", $messageText);
					$this->setStatus("awating_sistemaIrriguo");
					$this->reply('Che tipo di approvvigionamento irriguo utilizzi?');
					break;

				case "awating_sistemaIrriguo":
					$this->setField("sistema_irriguo", $messageText);
					$this->setField("completed_profile", true);
					$this->setStatus(0);
					$this->reply('Perfetto! Grazie per aver completato il tuo profilo');
					$this->sendMainMenu();
					break;
			}

			//set Message
			if ($messageText == "ciao") {
				$this->setStatus(null);
				$answer = "Ciao, sono un assistente virtuale. Al momento posso aiutarti nel darti informazioni generali sulle irrigazioni deficitarie (ID)";
				$this->reply($this->sender_id, $answer);
				$this->sendMainMenu();
			}
		} elseif (array_key_exists('postback', $input['entry'][0]['messaging'][0])) {

			$this->saveMessage();

			$postaback_data = $input['entry'][0]['messaging'][0]['postback']['payload'];

			switch ($postaback_data) {
				case 'MENU':
					$this->sendMainMenu();
					break;
				case 'MORE':
					$this->askToGoToMenu();
					break;
				case 'BYE':
					$this->sendGoodbye();
					$this->feedback();
					break;

					//General info
				case 'GENERAL_INFO':
					$this->sendGeneralInfo();
					break;
				case 'INFO_DEFINITION':
					$this->sendInfoDefinition();
					break;
				case 'INFO_IMPORTANCE':
					$this->sendInfoImportance();
					break;
				case 'INFO_RISK':
					$this->sendInfoRisk();
					break;
				case 'INFO_ADVANTAGE':
					$this->sendInfoAdvantage();
					break;
				case 'AMBIENTAL_ADVANTAGE':
					$this->sendAmbientAdvantage();
					break;
				case 'AGRONOMIST_ADVANTAGE':
					$this->sendAgronomistsAdvantage();
					break;
				case 'ECONOMICS_ADVANTAGE':
					$this->sendEconomicsAdvantage();
					break;

					//Tecnique
				case 'TECNIQUE':
					$this->sendTecnique();
					break;
				case 'TECHNIQUE_DI':
					$this->sentTecniqueDI();
					break;
				case 'TECHNIQUE_RDI':
					$this->sentTecniqueRDI();
					break;
				case 'TECHNIQUE_PRD':
					$this->sentTecniquePRD();
					break;

				case 'MORE_TECNIQUE_INFO_DI':
				case 'MORE_TECNIQUE_INFO_RDI':
				case 'MORE_TECNIQUE_INFO_PRD':
					$link = $this->sentTecniqueURL(str_replace('MORE_TECNIQUE_INFO_', '', $postaback_data));
					$this->reply($link);
					break;


					//Crops
				case 'CROPS':
					$this->sendCrops();
					break;
				case 'CROPS_ORTICOLE':
					$this->sendOrticoleCrops();
					break;
				case 'CROPS_ERBACEE':
					$this->sendErbaceeCrops();
					break;
				case 'CROPS_ARBOREE':
					$this->sendArboreeCrops();
					break;

				case 'INFO_DI':
					$this->askInfoTecnique();
					break;

				case 'MORE_CROPS_INFO_ORTICOLE':
				case 'MORE_CROPS_INFO_ERBACEE':
				case 'MORE_CROPS_INFO_ARBOREE':
					$link = $this->sentCropsURL(str_replace('MORE_CROPS_INFO_', '', $postaback_data));
					$this->reply($link);
					break;


					//Conditions
				case 'CONDITIONS':
					$this->sendConditions();
					break;
				case 'CROP_TYPES':
					$this->sendCropTypes();
					break;
					//Crop Types
				case 'CROP_TYPES_MEDIO_IMPASTO':
					$this->sendCropTypeMedioImpasto();
					break;
				case 'CROP_TYPES_ARGILLOSO':
					$this->sendCropTypeArgilloso();
					break;
				case 'CROP_TYPES_SABBIOSO':
					$this->sendCropTypeSabbioso();
					break;
				case 'CROP_TYPES_LIMOSO':
					$this->sendCropTypeLimoso();
					break;

				case 'CROP_IMPROVEMENT_ARGILLOSO':
				case 'CROP_IMPROVEMENT_SABBIOSO':
				case 'CROP_IMPROVEMENT_LIMOSO':
					$link = $this->sentImprovementURL(str_replace('CROP_IMPROVEMENT_', '', $postaback_data));
					$this->reply($link);
					break;

				case 'WATER_QUALITY':
					$this->sendWaterQuality();
					break;
					//Water Quality Types
				case 'WATER_QUALITY_CONVENZIONALE':
					$this->sendWaterQualityConvenzionale();
					break;
				case 'WATER_QUALITY_REFLUA':
					$this->sendWaterQualityReflua();
					break;
				case 'WATER_QUALITY_SALMASTRA':
					$this->sendWaterQualitySalmastra();
					break;
                case 'SALMASTRA_TABLE':
                    $this->reply("Qui è presente la tabella 👇\nhttps://www.isprambiente.gov.it/files/acqua/qualita-delle-acque-di-transizione.pdf");
                    break;
				case 'WATER_SUPPLY':
					$this->sendWaterSupply();
					break;
					//Water Supply Types
				case 'WATER_SUPPLY_PRIVATO_DOMANDA':
					$this->sendWaterSupplyPrivato();
					break;
				case 'WATER_SUPPLY_TURNI':
					$this->sendWaterSupplyTurni();
					break;

				case 'WATER_SYSTEM':
					$this->sendWaterSystem();
					break;
					//Water System Types
				case 'WATER_SYSTEM_ASP_SCORR':
					$this->sendWaterSystemAspScorr();
					break;
				case 'WATER_SYSTEM_MICROPORT':
					$this->sendWaterSystemMircoport();
					break;

					//parametri
				case 'PARAMETERS':
					$this->sendParameters();
					break;
				case 'CROPS_PARAM':
					$this->sendCropParameters();
					break;
				case 'CROPS_PARAM_YES':
					$this->sendCropsParamTypes();
					break;
				case 'CROPS_PARAM_LAI':
					$this->cropsParamLAI();
					break;
				case 'CROPS_PARAM_SOGLIA':
					$this->cropsParamSoglia();
					break;
				case 'HYDRO_PARAM':
					$this->sendHydroParameters();
					break;
				case 'HYDRO_PARAM_LINK':
					$this->showHydroParamLink();
					break;
				case 'INFO_DSS':
					$this->dssInformation();
					break;
				case 'WEATHER_PARAM':
					$this->sendWeatherParameters();
					break;
				case 'METEO_LINK':
					$this->reply("Ecco il link per ricercare la stazione meteo più vicina a te! 👇\nhttps://www.ilmeteo.it/");
					break;
				case 'WEATHER_STATION':
					$this->askInstallWeatherStation();
					break;
				case 'WEATHER_STATION_LINK':
					$this->reply("Qui trovi un approfondimento su come installare una stazione meteo 👇\nhttp://www.miglioristazionimeteo.it/le-nostre-guide/dove-installare-la-stazione-meteo/");
					break;
				case 'AGRO_PARAM':
					$this->sendAgroParameters();
					break;
				case 'PREVISION_PARAM':
					$this->sendPrevisionParameters();
					break;
				case 'LINK_DSS':
					$this->showDssLink();
					break;

					//PROFILAZIONE
				case 'PROFILE':
					$this->UserProfile();
					break;

				case 'CREATE_PROFILE':
					$this->createProfile();
					break;

				case 'SHOW_PROFILE':
					$this->showProfile();
					break;

					//FEEDBACK
				case 'FEEDBACK_POSITIVE':
					$this->reply('Grazie per la tua valutazione positiva, cercheremo di migliorare sempre di più!');
					break;
				case 'FEEDBACK_DISCRETA':
					$this->reply('Grazie per la tua valutazione discreta, cercheremo di migliorare sempre di più!');
					break;
				case 'FEEDBACK_NEGATIVE':
					$this->reply('Grazie per la tua valutazione negativa, ci impegneremo a fare di meglio');
					break;


				default:
					$this->reply('Questa operazione non è prevista. Il mio sviluppatore è scemo e se ne sarà dimenticato');
			}
		}
	}

	function sendMainMenu()
	{

		$this->reply('Ciao, benvenuto!👋 Sono un chatbot sviluppato da poco per fornire supporto personalizzato alle attività di irrigazione');

		$this->reply('Come posso aiutarti? Ecco le opzioni al momento disponibili. Scegline una:', [
			[
				"type" => "postback",
				"payload" => "GENERAL_INFO",
				"title" => "Informazioni generali"
			],
			[
				"type" => "postback",
				"payload" => "TECNIQUE",
				"title" => "Tecniche di ID"
			],
			[
				"type" => "postback",
				"payload" => "CROPS",
				"title" => "Ordinamento colturale"
			],

		]);

		$this->reply('‎‏‏‎ ‎‎‎', [
			[
				"type" => "postback",
				"payload" => "CONDITIONS",
				"title" => "Condizioni aziendali"
			],
			[
				"type" => "postback",
				"payload" => "PARAMETERS",
				"title" => "Parametri per le ID"
			],
			[
				"type" => "postback",
				"payload" => "PROFILE",
				"title" => "Profilo Aziendale"
			],
		]);
	}

	//GeneralInfo functions
	function sendGeneralInfo()
	{
		$this->reply('Da qui puoi avere informazioni generali sulla definizione di ID, sull\'importanza, sui vantaggi e sui rischi. Quale ti interessa', [
			[
				"type" => "postback",
				"payload" => "INFO_DEFINITION",
				"title" => "Definizione"
			],
			[
				"type" => "postback",
				"payload" => "INFO_IMPORTANCE",
				"title" => "Importanza"
			],
		]);
		$this->reply('‎‏‏‎ ‎‎‎', [
			[
				"type" => "postback",
				"payload" => "INFO_RISK",
				"title" => "Rischi"
			],
			[
				"type" => "postback",
				"payload" => "INFO_ADVANTAGE",
				"title" => "Vantaggi"
			],
		]);
	}

	function UserProfile()
	{
		$this->reply('Da qui puoi: ', [
			[
				"type" => "postback",
				"payload" => "CREATE_PROFILE",
				"title" => "Creare Profilo"
			],
			[
				"type" => "postback",
				"payload" => "SHOW_PROFILE",
				"title" => "Mostare Pofilo"
			],
		]);
	}

	function createProfile()
	{
		$this->setField("completed_profile", false);
		$this->setStatus('awating_name');
		$this->reply('Qual è il nome della tua azienda?');
	}

	function showProfile()
	{

		if ($this->getField("completed_profile") == false) {
			$this->reply('Il tuo profilo non è ancora presente. Inseriscilo');
			$this->createProfile();
			return;
		}

		$userInfo = $this->users_db->getChild($this->sender_id)->getValue();
		$msg = '';

		$msg .= 'Nome azienda: ' . $userInfo['company_name'] . "\n";
		$msg .= 'Estensione aziendale: ' . $userInfo['company_extension'] . "\n";
		$msg .= 'Specie colturale: ' . $userInfo['coltures_type'] . "\n";
		$msg .= 'Superficie colturale: ' . $userInfo['coltures_extension'] . "\n";
		$msg .= 'Sistema Irriguo: ' . $userInfo['sistema_irriguo'] . "\n";

		$this->reply($msg);
	}

	function getStatus()
	{
		return $this->getField("status");
	}

	function setStatus($status)
	{
		$this->setField("status", $status);
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

	function sendInfoDefinition()
	{

		$this->reply('L\'irrigazione Deficitaria consiste nell\'applicare volumi irrigui stagionali ridotti ossia al di sotto del pieno soddisfacimento del fabbisogno irriguo della coltura con l’obiettivo di aumentare l’efficienza d’uso dell’acqua e ridurre al minimo la perdita di produzione.');
		$this->askToGoToMenu();
	}
	function sendInfoImportance()
	{
		$this->reply('L\' irrigazione deficitaria è molto importante. La grande richiesta di acqua per l\'irrigazione e la prevista limitazione dei prelievi irrigui (previsti dai cambiamenti climatici) anche nel caso della distribuzione consortile, costringono gli agricoltori a razionalizzare l\'uso dell\'acqua per sostenere la resa e la qualità delle produzioni');
		$this->askToGoToMenu();
	}
	function sendInfoRisk()
	{
		$this->reply("I rischi che si incorrono, riguardano la perdita di produzione e peggioramento della qualità se applicata:\n• A colture che non tollerano carenze idriche nel suolo;\n• Durante fasi fenologiche sensibili alla carenza idrica;\n• Con volumi irrigui non appropriati");
		$this->askToGoToMenu();
	}
	function sendInfoAdvantage()
	{
		$this->reply("L'irrigazione deficitaria presenta numerosi vantaggi:\n• Riduzione limita della produzione\n• Riduzione significativa dei volumi irrigui stagionali\n• Migliora l’efficienza irrigua, ovvero la produzione per metro cubo di acqua irrigua applicata.\n• Migliorare la qualità del prodotto\n• Contiene i rischi fito-sanitari\n• Riduzione dei costi di produzione");
		$this->reply('Da qui puoi avere maggiori informazioni sui vantaggi ambientali, agronomici ed economici. Quale ti interessa', [
			[
				"type" => "postback",
				"payload" => "AMBIENTAL_ADVANTAGE",
				"title" => "Vantaggi Ambientali"
			],
			[
				"type" => "postback",
				"payload" => "AGRONOMIST_ADVANTAGE",
				"title" => "Vantaggi Agronomici"
			],
			[
				"type" => "postback",
				"payload" => "ECONOMICS_ADVANTAGE",
				"title" => "Vantaggi Economici"
			],
		]);
	}
	function sendAmbientAdvantage()
	{
		$this->reply("Tra i vantaggi ambientali troviamo:\n• Tesaurizzazione delle risorse idriche\n• La riduzione delle perdite di acqua per drenaggio con lisciviazione degli elementi nutritivi\n• Riduzione dell’impronta idrica e di carbonio del prodotto agricolo in uscita dal gate aziendale");
		$this->askToGoToMenu();
	}
	function sendAgronomistsAdvantage()
	{
		$this->reply("Tra i vantaggi ambientali troviamo:\n• Tendenza al miglioramento della qualità dei prodotti (in termini di tenore zuccherino, serbevolzza e shelf life)\n• Le piante risultano meno lussureggianti e pertanto meno aggredibili dai parassiti\n• Contenimento della flora avventizia");
		$this->askToGoToMenu();
	}
	function sendEconomicsAdvantage()
	{
		$this->reply("• Qualora le perdite di produzioni siano trascurabili, diminuisce il costo di produzione per il contenimento delle spese di esercizio dell’irrigazione e dell’acqua, dei trattamenti antiparassitari e per il controllo delle malerbe");
		$this->askToGoToMenu();
	}

	//Tecnique functions
	function sendTecnique()
	{

		$this->reply('Da qui puoi avere brevi informazioni sulle tecniche di ID, in particolare sulla DI, RDI e PRD. Quale ti interessa', [
			[
				"type" => "postback",
				"payload" => "TECHNIQUE_DI",
				"title" => "DI"
			],
			[
				"type" => "postback",
				"payload" => "TECHNIQUE_RDI",
				"title" => "RDI"
			],
			[
				"type" => "postback",
				"payload" => "TECHNIQUE_PRD",
				"title" => "PRD"
			],
		]);
	}
	function sentTecniqueDI()
	{
		$this->reply('La tecnica DI (Deficit Irrigation) consiste ridurre i volumi irrigui di ogni irrigazione per una percentuale per quasi tutto il ciclo colturale tale da non fare diminuire il contenuto idrico del suolo al di sotto di una soglia di umidità (soglia di intervento) oltre la quale si determinerebbero perdite di produzione');
		$this->askMoreTecniqueInfo("DI");
	}
	function sentTecniqueRDI()
	{
		$this->reply('La tecnica RDI (Regulated Deficit Irrigation) consiste nell’ interrompere o ridurre i volumi irrigui durante determinate fasi fenologiche in cui la coltura è maggiormente resistente a condizioni di carenza idrica');
		$this->askMoreTecniqueInfo("RDI");
	}
	function sentTecniquePRD()
	{
		$this->reply('La tecnica PRD (Partial Root Drying), consiste nel somministrare i volumi idrici soltanto ad una parte dell’apparato radicale, in modo da creare una zona umida contrapposta ad una zona asciutta. Il risparmio irriguo è determinato dalla effettiva riduzione della superficie irrigata');
		$this->askMoreTecniqueInfo("PRD");
	}
	function askMoreTecniqueInfo($tecniqueType)
	{
		$this->reply('Vuoi approfondimenti sulle tecniche di ID?', [
			[
				"type" => "postback",
				"payload" => "MORE_TECNIQUE_INFO_$tecniqueType",
				"title" => "Sì"
			],
			[
				"type" => "postback",
				"payload" => "MORE",
				"title" => "No"
			],
		]);
	}

	function sentTecniqueURL($tecniqueType)
	{
		return [
			'DI' => "Qui trovi degli approfondimenti sulle tecniche DI 👇\nhttp://www.fao.org/3/y3655e/y3655e03.htm",
			'RDI' => "Qui trovi degli approfondimenti sulle tecniche RDI 👇\nhttps://link.springer.com/article/10.1007/s13593-015-0338-6",
			'PRD' => "Qui trovi degli approfondimenti sulle tecniche PRD 👇\nhttp://www.bio21.bas.bg/ipp/gapbfiles/essa-03/03_essa_164-171.pd",
		][$tecniqueType];
	}

	//Crops functions
	function sendCrops()
	{

		$this->reply('Da qui puoi avere informazioni su quele ordinamento colturale si possono applicare le tecniche di ID. Quale ordinamento colturale ti interessa?', [
			[
				"type" => "postback",
				"payload" => "CROPS_ORTICOLE",
				"title" => "Orticole"
			],
			[
				"type" => "postback",
				"payload" => "CROPS_ERBACEE",
				"title" => "Erbacee"
			],
			[
				"type" => "postback",
				"payload" => "CROPS_ARBOREE",
				"title" => "Arboree"
			],
		]);
	}
	function sendOrticoleCrops()
	{
		$this->reply('Per la maggior parte delle colture orticole in genere è sconsigliato applicare tecniche di ID. Per il pomodoro da industria, invece, ultime evidenze scientifiche dimostrano che è possibile applicare la regulated deficit irrigation (RDI)');
		$this->askMoreCropsInfo("ORTICOLE");
	}
	function sendErbaceeCrops()
	{
		$this->reply('Per le colture erbacee (Mais, Soia, Barbabietola da zucchero, Girasole) si può applicare la ID riducendo i volumi irrigui di ogni irrigazione per una determinata percentuale, rispetto alla piena irrigazione');
		$this->askMoreCropsInfo("ERBACEE");
	}
	function sendArboreeCrops()
	{
		$this->reply('Per le arboree è preferibile la RDI in modo da ridurre o interrompere le irrigazioni nelle fasi fenologiche meno sensibili. Ci sono anche arboree dove è possibile applicare la Partial root drying (PRD');
		$this->askMoreCropsInfo("ARBOREE");
	}
	function askMoreCropsInfo($cropsType)
	{
		$this->reply('Vuoi approfondimenti sulla coltura?', [
			[
				"type" => "postback",
				"payload" => "MORE_CROPS_INFO_$cropsType",
				"title" => "Sì"
			],
			[
				"type" => "postback",
				"payload" => "INFO_DI",
				"title" => "No"
			],
		]);
	}

	function sentCropsURL($cropsType)
	{
		return [
			'ORTICOLE' => "Qui trovi un approfondimento sulle colture orticole 👇\nhttp://agromap.arsia.toscana.it/agri14/docs/disci/Colture_orticole.pdf",
			'ERBACEE' => "Qui trovi un approfondimento sulle colture erbacee 👇\nhttps://www.agraria.org/coltivazionierbacee.htm",
			'ARBOREE' => "Qui trovi un approfondimento sulle colture arboree 👇\nhttp://www.gruppo-panacea.it/home/it/biomasse-residuali/101-residui-di-potatura-per-fini-energetici/le-colture-arboree-in-italia/110-le-colture-arboree-in-italia",
		][$cropsType];
	}

	function askInfoTecnique()
	{
		$this->reply('Vuoi sapere cosa vuol dire DI, RDI e PRD?', [
			[
				"type" => "postback",
				"payload" => "TECNIQUE",
				"title" => "Sì"
			],
			[
				"type" => "postback",
				"payload" => "MORE",
				"title" => "No"
			],
		]);
	}

	//Conditions functions
	function sendConditions()
	{

		$this->reply("Da qui puoi avere informazioni sulle condizioni aziendali che influenzano la ID come il tipo di terreno, la qualità dell\'acqua, l\'approvvigionamento irriguo e sistema irriguo.\nQuale ti interessa tra quelli appena elencati?", [
			[
				"type" => "postback",
				"payload" => "CROP_TYPES",
				"title" => "Tipo di terreno"
			],
			[
				"type" => "postback",
				"payload" => "WATER_QUALITY",
				"title" => "Qualità dell'acqua"
			],
		]);

		$this->reply(' ‎‎‎', [
			[
				"type" => "postback",
				"payload" => "WATER_SUPPLY",
				"title" => "Approvvigionamento"
			],
			[
				"type" => "postback",
				"payload" => "WATER_SYSTEM",
				"title" => "Sistema Irriguo"
			],
		]);
	}

	/*TIPI DI TERRENO*/
	function sendCropTypes()
	{
		$this->reply('Quale tipo di terreno ti interessa?', [
			[
				"type" => "postback",
				"payload" => "CROP_TYPES_MEDIO_IMPASTO",
				"title" => "Medio Impasto"
			],
			[
				"type" => "postback",
				"payload" => "CROP_TYPES_ARGILLOSO",
				"title" => "Argilloso"
			],
		]);
		$this->reply('‎‏‏‎ ‎‎‎', [
			[
				"type" => "postback",
				"payload" => "CROP_TYPES_SABBIOSO",
				"title" => "Sabbioso"
			],
			[
				"type" => "postback",
				"payload" => "CROP_TYPES_LIMOSO",
				"title" => "Limoso"
			],
		]);
	}
	function sendCropTypeMedioImpasto()
	{
		$this->reply('I terreni a medio impasto (argilla: 10-25%; limo: 25-35%; sabbia 35-55%) sono in genere profondi e ben strutturati con buona riserva idrica per cui sono più indicati alle tecniche di ID');
		$this->askToGoToMenu();
	}
	function sendCropTypeArgilloso()
	{
		$this->reply('Nei terreni argillosi (argilla: >30%) c’è il rischio che si verificano condizioni di asfissia radicale');
		$this->askCropsImprovement("ARGILLOSO");
	}
	function sendCropTypeSabbioso()
	{
		$this->reply('Nei terreni sabbiosi (sabbia: >65%) c’è il limite dei turni irrigui ristretti determinati dal veloce esaurimento della riserva idrica');
		$this->askCropsImprovement("SABBIOSO");
	}
	function sendCropTypeLimoso()
	{
		$this->reply('Nei terreni limosi (limo: >70%) c’è il rischio che si verificano condizioni di asfissia radicale');
		$this->askCropsImprovement("LIMOSO");
	}
	function askCropsImprovement($type)
	{
		$this->reply('Vuoi sapere come migliorare le condizioni del terreno?', [
			[
				"type" => "postback",
				"payload" => "CROP_IMPROVEMENT_$type",
				"title" => "Sì"
			],
			[
				"type" => "postback",
				"payload" => "MORE",
				"title" => "No"
			],
		]);
	}
	function sentImprovementURL($type)
	{
		return [
			'ARGILLOSO' => "Qui trovi come migliorare il terreno argilloso 👇\nhttps://www.nonsprecare.it/come-migliorare-terreno-argilloso",
			'SABBIOSO' => "Qui trovi come migliorare le condizioni del terreno sabbioso👇\nhttps://www.ortodacoltivare.it/terra-orto/sabbioso.html",
			'LIMOSO' => "Qui trovi come migliorare il terreno limoso 👇\nhttps://www.coltivazionebiologica.it/terreno-agricolo/",
		][$type];
	}

	/*QUALITA' DELL'ACQUA*/
	function sendWaterQuality()
	{
		$this->reply('Quale acqua ti interessa?', [
			[
				"type" => "postback",
				"payload" => "WATER_QUALITY_CONVENZIONALE",
				"title" => "Convenzionale"
			],
			[
				"type" => "postback",
				"payload" => "WATER_QUALITY_REFLUA",
				"title" => "Refula"
			],
			[
				"type" => "postback",
				"payload" => "WATER_QUALITY_SALMASTRA",
				"title" => "Salmastra"
			],
		]);
	}
	function sendWaterQualityConvenzionale()
	{
		$this->reply('Per le acque convenzionali, il maggior problema è legato al contenuto in calcare che potrebbe ostruire i gocciolatori. Sono consigliate pulizie periodiche con composti acidi nell’impianto di irrigazione');
		$this->askToGoToMenu();
	}
	function sendWaterQualityReflua()
	{
		$this->reply('Per le acque reflue, le attuali restrizioni legislative consentono di ottenere acque raffinate di buona qualità. Il maggior problema è legato alla presenza di materiale in sospensione potrebbero ostruire i gocciolatori. Sono consigliati impianti di filtrazione a monte del sistema irriguo');
		$this->askToGoToMenu();
	}
	function sendWaterQualitySalmastra()
	{
		$this->reply('Per le acque salmastre, se la coltura risulta tollerante bisogna aumentare i volumi irrigui di una quantità (frazione di lisciviazione) necessaria per dilavare i sali in eccesso portati dall’acqua stessa');
		$this->showTable();
	}
	function showTable()
	{
		$this->reply('Vuoi che ti illustri una tabella sulla sensibilità delle colture alle acque salmastre?', [
			[
				"type" => "postback",
				"payload" => "SALMASTRA_TABLE",  //funzione per mostrare la tabella
				"title" => "Sì"
			],
			[
				"type" => "postback",
				"payload" => "MORE",
				"title" => "No"
			],
		]);
	}

	/*TIPI DI APPROVVIGIONAMENTO*/
	function sendWaterSupply()
	{
		$this->reply('Quale approvvigionamento irriguo ti interessa?', [
			[
				"type" => "postback",
				"payload" => "WATER_SUPPLY_PRIVATO_DOMANDA",
				"title" => "Privato o a domanda"
			],
			[
				"type" => "postback",
				"payload" => "WATER_SUPPLY_TURNI",
				"title" => "Consortile a turni"
			],
		]);
	}
	function sendWaterSupplyPrivato()
	{
		$this->reply('L’approvviggionamento privato o consortile a domanda è l’ideale per l’applicazione delle tecniche di ID, in quanto si ha la possibilità di irrigare tempestivamente');
		$this->askToGoToMenu();
	}
	function sendWaterSupplyTurni()
	{
		$this->reply('L’approvviggionamento consortile a turni si deve predisporre uno stoccaggio dell’acqua per far fronte ai volumi e turni irrigui');
		$this->askToGoToMenu();
	}

	/*TIPI DI SISTEMA IRRIGUO*/
	function sendWaterSystem()
	{
		$this->reply('Quale sistema irriguo interessa?', [
			[
				"type" => "postback",
				"payload" => "WATER_SYSTEM_ASP_SCORR",
				"title" => "Aspersione/Scorrimento"
			],
			[
				"type" => "postback",
				"payload" => "WATER_SYSTEM_MICROPORT",
				"title" => "Sistemi Microportata"
			],
		]);
	}
	function sendWaterSystemAspScorr()
	{
		$this->reply('I sistemi per aspersione e/o per scorrimento sono sconsigliati per l’applicazione della ID a causa della bassa efficienza');
		$this->bestWaterSystem();
	}
	function sendWaterSystemMircoport()
	{
		$this->reply('I sistemi a microporata sono i più indicati per l’applicazione della ID grazie alla elevata efficienza');
		$this->askToGoToMenu();
	}
	function bestWaterSystem()
	{
		$this->reply('Vuoi sapere il migliore sistema irriguo pe applicare la ID?', [
			[
				"type" => "postback",
				"payload" => "WATER_SYSTEM_MICROPORT",
				"title" => "Sì"
			],
			[
				"type" => "postback",
				"payload" => "MORE",
				"title" => "No"
			],
		]);
	}

	//Parameters functions
	function sendParameters()
	{

		$this->reply('Qui puoi avere informazioni sui parametri necessari per determinare i volumi e turni irrigui attraverso un DSS. Essi sono i parametri colturali, agromanagement, idrologici del terreno e meteorologici anche in base alle previsioni a brevemedio termine.
            Vuoi avere info su questi parametri? Indicamene uno', [
			[
				"type" => "postback",
				"payload" => "CROPS_PARAM",
				"title" => "Parametri colturali"
			],
			[
				"type" => "postback",
				"payload" => "HYDRO_PARAM",
				"title" => "Parametri idrologici"
			],
		]);

		$this->reply(' ‎‎‎', [
			[
				"type" => "postback",
				"payload" => "WEATHER_PARAM",
				"title" => "Parametri meteorologici"
			],
			[
				"type" => "postback",
				"payload" => "AGRO_PARAM",
				"title" => "Agromanagement"
			],
			[
				"type" => "postback",
				"payload" => "PREVISION_PARAM",
				"title" => "Previsioni breve-medio termine"
			],
		]);
	}

	/*PARAMETRI COLTURALI*/
	function sendCropParameters()
	{
		$this->reply('I parametri colturali sono specifici per ciascuna coltura e raccolti in un database. Riguardano principalmente la superficie fogliare (LAI), l’altezza della pianta, la profondità della radice, soglia di intervento irrigo');
		$this->cropsParamMeaning();
	}
	function cropsParamMeaning()
	{
		$this->reply('Vuoi sapere il significato di questi parametri colturali?', [
			[
				"type" => "postback",
				"payload" => "CROPS_PARAM_YES",
				"title" => "Sì"
			],
			[
				"type" => "postback",
				"payload" => "MORE",
				"title" => "No"
			],
		]);
	}
	function sendCropsParamTypes()
	{
		$this->reply('Scegli il tipo di parametro colturale di cui vuoi avere informazioni', [
			[
				"type" => "postback",
				"payload" => "CROPS_PARAM_LAI",
				"title" => "LAI"
			],
			[
				"type" => "postback",
				"payload" => "CROPS_PARAM_SOGLIA",
				"title" => "Intervento Irriguo"
			],
		]);
	}
	function cropsParamLAI()
	{
		$this->reply('Il LAI rappresenta l’indice di area fogliare ossia la densita fogliare in m2 su 1 m2 di superficie colturale');
		$this->dssInformation();
	}
	function cropsParamSoglia()
	{
		$this->reply('La soglia di intervento irriguo rappresnta il livello di umidità del terreno al quale bisogna intervenire con l’irrigazione (eventualmente mostrare grafico)');
		$this->dssInformation();
	}

	/*PARAMETRI IDROLOGICI*/
	function sendHydroParameters()
	{
		$this->reply('I parametri idrologici del terreno sono rappresentati principalmente dalla capacità idrica di campo, punto di appassimento. Se questi dati mancano possono essere ricavati a partire da dati della granulometria del terreno. Questi dati, se non indicati dall’utente sono ottenuti da un database nazionale');
		$this->hydroParamMeaning();
	}
	function hydroParamMeaning()
	{
		$this->reply('Vuoi maggiori informazioni sul significato dei parametri idrologici del terreno?', [
			[
				"type" => "postback",
				"payload" => "HYDRO_PARAM_LINK",
				"title" => "Sì"
			],
			[
				"type" => "postback",
				"payload" => "INFO_DSS",
				"title" => "No"
			],
		]);
	}
	function showHydroParamLink()
	{
		$this->reply("Qui trovi un approfondimento sul significato dei parametri idrologici del terreno:\nhttp://www.filecciageologia.it/Download/Lezioni_idrogeologia/5_parametri_idrogeologici.pdf ");
	}

	/*PARAMETRI METEOROLOGICI*/
	function sendWeatherParameters()
	{
		$this->reply('I parametri meteorologici sono rappresentati principalmente dalla temperatura e umidità dell’aria, velocità del vento, radiazione solare e precipitazione. Sono dati forniti da stazioni meteo locali o di proprietà aziendale');
		$this->askWeatherStation();
	}

	function askWeatherStation()
	{
		$this->reply('Vuoi sapere la stazione meteo vicino alla tua zona o azienda?', [
			[
				"type" => "postback",
				"payload" => "METEO_LINK",
				"title" => "Sì"
			],
			[
				"type" => "postback",
				"payload" => "WEATHER_STATION",
				"title" => "No"
			],
		]);
	}
	function askInstallWeatherStation()
	{
		$this->reply('Vuoi info su come installare una stazione meteo', [
			[
				"type" => "postback",
				"payload" => "WEATHER_STATION_LINK",
				"title" => "Sì"
			],
			[
				"type" => "postback",
				"payload" => "INFO_DSS",
				"title" => "No"
			],
		]);
	}

	/*PARAMETRI COLTURALI*/
	function sendAgroParameters()
	{
		$this->reply('L’agromanagement sono dati colturali richiesti dal DSS e inseriti dall’utente come la data di semina, il sesto di impianto o n. di piante ad Ha, le irrigazioni eseguite, il tipo di impianto irriguo, data di raccolta');
		$this->dssInformation();
	}

	/*PARAMETRI COLTURALI*/
	function sendPrevisionParameters()
	{
		$this->reply('Le previsioni a breve-medio termine sui volumi irrigui da somministrare si basa sulle previsioni di dati meteo a breve (3 gg) e medio termine (max 15 gg) forniti dal meteo.it');
		$this->dssInformation();
	}
	function dssInformation()
	{
		$this->reply('Vuoi maggiori informazioni su come funziona il DSS', [
			[
				"type" => "postback",
				"payload" => "LINK_DSS",
				"title" => "Sì"
			],
			[
				"type" => "postback",
				"payload" => "MORE",
				"title" => "No"
			],
		]);
	}
	function showDssLink()
	{
		$this->reply("Qui trovi come funziona il il DSS:\n https://www.agricolus.com/dss-decision-support-system/");
	}

	/*FEEDBACK*/
	function feedback()
	{
		$this->reply('Com\'è stata la tua esperienza usando questo bot? Fai la tua valutazione 😀', [
			[
				"type" => "postback",
				"payload" => "FEEDBACK_POSITIVE",
				"title" => "Positiva"
			],
			[
				"type" => "postback",
				"payload" => "FEEDBACK_DISCRETA",
				"title" => "Discreta"
			],
			[
				"type" => "postback",
				"payload" => "FEEDBACK_NEGATIVE",
				"title" => "Negativa"
			],
		]);
	}



	//Utilities functions
	function askToGoToMenu()
	{
		$this->reply('Vuoi altre Informazioni sulla ID?', [
			[
				"type" => "postback",
				"payload" => "MENU",
				"title" => "Sì"
			],
			[
				"type" => "postback",
				"payload" => "BYE",
				"title" => "No"
			],
		]);
	}
	function sendGoodbye()
	{
		$this->reply('Va bene! A presto');
	}


	function reply($text, $buttons = null)
	{
		if ($buttons) {

			$message = [
				'attachment' => [
					'type' => 'template',
					'payload' => [
						"template_type" => "button",
						"text" => $text,
						"buttons" => $buttons
					]
				]
			];

			// $this->debug(json_encode($message));

		} else {
			$message = ['text' => $text];
		}

		$body = [
			'recipient' => ['id' => $this->sender_id],
			'message' => $message
		];


		try {
			$ch = curl_init('https://graph.facebook.com/v7.0/me/messages?access_token=' . $this->access_token);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
			curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
			$result = curl_exec($ch);
			// debug('curl eseguito ' . json_encode($result));
			curl_close($ch);
		} catch (Exception $e) {
			$this->debug($e->getMessage());
		}
	}

	function debug($text)
	{
		$file = './log.txt';
		file_put_contents($file, date("H:i") . ' ' . $text . "\n", FILE_APPEND | LOCK_EX);
	}

	function saveMessage()
	{

		$timestamp = round(microtime(true) * 1000);
		$last_message = $this->getLastMessage();
		$time_between_msgs = $timestamp - $last_message['timestamp'];

		$message_data = [
			'intent' => $this->message_payload['postback']['payload'],
			'origin_app' => 'facebook',
			'origin_user_id' => $this->sender_id,
			'timestamp' => $timestamp,
			'time_between_msgs' => $time_between_msgs
		];

		$this->messages_db->getChild($this->message_id)->set($message_data);
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
