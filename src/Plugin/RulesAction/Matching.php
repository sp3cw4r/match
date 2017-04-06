<?php
/**
*@file
*Contains \Drupal\match\Plugin\RulesAction\Matching.
*/

namespace Drupal\match\Plugin\RulesAction;
use Drupal\rules\Core\RulesActionBase;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
*
* Performs a Matching
*
*@RulesAction(
*  id = "rules_matching",
*  label = @Translation("Match Content"),
*  category = @Translation("Content"),
*  context = {
*       "entity" = @ContextDefinition("entity",
*        label = @Translation("Entity"),
*        description = @Translation("The content type to be compared")
*	)
*  }
*)
*/
class Matching extends RulesActionBase implements ContainerFactoryPluginInterface{

/**
   * The logger channel the action will write log messages to.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;
  /**
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;
  /**
   * Constructs a SendEmail object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Psr\Log\LoggerInterface $logger
   *   The alias storage service.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerInterface $logger, MailManagerInterface $mail_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = $logger;
    $this->mailManager = $mail_manager;
  }
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('rules'),
      $container->get('plugin.manager.mail')
    );
  }


/**
* Matches Content to 'Opportunities'
*
*@param $node
* represents the Student node being submitted
*/

protected function doExecute($node){

//getting all of the Opportunity type node ids, then storing all of the nodes in $opportunities
$nids = \Drupal::entityQuery('node')->condition('type', 'opportunity')->execute();
$opportunities = \Drupal\node\Entity\Node::loadMultiple($nids);
//initialize the $matchScore to 0
$matchScore = 0;
//Beginning of every email sent by the matching system
$message = "Welcome to the UPEISU Opportunity Matchmaker!\n\n";
//Initialize clubs email
$clubsEmail = "";
	//only run the following code if the $node submitted is of type Student
	//otherwise the code will break because of field names
	if($node->getType() == "student"){
		//loop through every opportunity node in the system for comparison
		while($opportunity = array_pop($opportunities))
		{
			$matchScore = 0; //reset score to 0 for every new $opportunity
			//store the opportunity's opportunity type in a variable
			$opOpType = $opportunity->get('field_op_opportunity_type')->get('0');

			//loop through every selected opportunity type for the Student node and compare to $opOpType
			foreach($node->get('field_opportunity_type') as $nodeOpType)
			{
				//if they are a match increase the $matchScore
				if($nodeOpType->getValue() == $opOpType->getValue()){
					$matchScore++;
				}
//				dpm($nodeOpType->getValue());
//				dpm($opOpType->getValue());
			}

			//only if the opportunity is an opportunity type that the Student is looking for
			if($matchScore >= 1){
				//get the Student's year of study
				$nodeYear = $node->get('field_year_of_study')->get('0');

				//loop through all of the opportunity's preferred years of study and compare to $nodeYear
				foreach($opportunity->get('field_preferred_year_of_study') as $opYear)
				{
					//if they are a match increase the $matchScore
					if($nodeYear->getValue() == $opYear->getValue()){
						$matchScore++;
					}
//				dpm($nodeYear->getValue());
//				dpm($opYear->getValue());
				}

				//get the Student's faculty
				$nodeFaculty = $node->get('field_faculty_or_school')->get('0');

				//loop through all of the opportunity's preferred facultys and compare to $nodeFaculty
				foreach($opportunity->get('field_preferred_faculty_or_schoo') as $opFaculty)
				{
					//if they are a match increase the $matchScore
					if($nodeFaculty->getValue() == $opFaculty->getValue()){
						$matchScore++;
					}
//				dpm($nodeFaculty->getValue());
//				dpm($opFaculty->getValue());
				}

				//this is a nested for loop that compares all of the Student's interests to all of the opportunity's interests
				foreach($node->get('field_interests') as $nodeInterest)
				{
					foreach($opportunity->get('field_op_interests') as $opInterest)
					{
						//if they are a match increase the $matchScore
						if($nodeInterest->getValue() == $opInterest->getValue())
							$matchScore++;
					}
				}

				dpm($opportunity->getTitle());
			dpm($matchScore);
//			dpm($opportunity->get('field_main_contact_name')->getValue());
//			dpm($opportunity->get('field_main_contact_email')->getValue());

			if($matchScore > 8){
				$message = $message . "Extremely Compatible Match\nYou have been matched to: " . $opportunity->getTitle() . " which is a " . $opOpType->get('value')->getValue() . "!\n";
			}
			elseif($matchScore > 4){
				$message = $message . "Highly Compatible Match\nYou have been matched to: " . $opportunity->getTitle() . " which is a " . $opOpType->get('value')->getValue() . "!\n";
			}
			elseif($matchScore > 1){
				$message = $message . "Moderately Compatible Match\nYou have been matched to: " . $opportunity->getTitle() . " which is a " . $opOpType->get('value')->getValue() . "!\n";
			}
			else{
				$message = $message . "Match to Opportunity Type Only\nYou have been matched to: " . $opportunity->getTitle() . " which is a " . $opOpType->get('value')->getValue() . ".\n";
			}

			//we are adding to the message based on the current opportunity type
			$message = $message . "The main contact information is provided below:\n";
			$message = $message . "Name: " . $opportunity->get('field_main_contact_name')->get('0')->get('value')->getValue() . "\tEmail: " . $opportunity->get('field_main_contact_email')->get('0')->get('value')->getValue() . "\n";
			$message = $message . "Typical meeting place: " . $opportunity->get('field_meeting_place')->get('0')->get('value')->getValue() . "\n\n";

			//storing all opportunity emails
			$clubsEmail = $clubsEmail . $opportunity->get('field_main_contact_email')->get('0')->get('value')->getValue() . ", ";


			}//end of if($matchScore>1)



		}//end of while for $opportunities

			dpm($message);
			//sending the email to the Student
			$langcode = LanguageInterface::LANGCODE_SITE_DEFAULT;
			    $params = [
      				'subject' => "Student Union Opportunity Matches",
      				'message' => $message,
    				];
			// Set a unique key for this mail.
    			$key = 'upeisu_opportunity_matchmaker_mail_' . $node->id();
			//storing the recipients that will receive the email
			$recipients = "clubs@upeisu.ca, ";
    			$studentEmail =  $node->get('field_email')->get('0')->get('value')->getValue();
    			$recipients = $recipients . $studentEmail;


			//sending the email based on our parameters and displaying success message in log file
    			$message = $this->mailManager->mail('rules', $key, $recipients, $langcode, $params);
    			if ($message['result']) {
    			  $this->logger->notice('Successfully sent email to %recipient', ['%recipient' => $recipients]);
    			}


			//Setting up and sending clubs/opportunities email
			$messageClubs = $node->getTitle() . " has matched to you through the UPEISU Opportunity Matchmaker! Contact information is below.\n";
			$messageClubs = $messageClubs . "Email: " . $studentEmail . "\tPhone: " . $node->get('field_preferred_phone_number')->get('0')->get('value')->getValue() . "\n\n";

			dpm($messageClubs);

			//duplicating variables under different name for second email
			$langcodeClub = LanguageInterface::LANGCODE_SITE_DEFAULT;
			    $paramsClub = [
			      'subject' => "Student Matched with Your Opportunity",
			      'message' => $messageClubs,
			    ];
			// Set a unique key for this mail.
    			$keyClub = 'upeisu_opportunity_matchmaker_mail_' . $this->getPluginId();
			//Finalizing the email list with the clubs coordinator email
    			$clubsEmail = $clubsEmail . "clubs@upeisu.ca";

			//sending the email and displaying success message in log file
    			$message = $this->mailManager->mail('rules', $keyClub, $clubsEmail, $langcodeClub, $paramsClub);
    			if ($message['result']) {
    			  $this->logger->notice('Successfully sent email to %recipient', ['%recipient' => $clubsEmail]);
    			}


	}//end of if student

  }//end of doExecute

}//end of class

