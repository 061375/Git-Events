<?php
namespace Drupal\git_events\Plugin\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Form\FormStateInterface;
/**
 *
 * GitEventsBlock
 *
 * @author Jeremy Heminger <j.heminger@061375.com>
 * @version 1.0.3
 *
 * */


/**
 * Provides a 'GIT Events' Block
 *
 * @Block(
 *   id = "git_events",
 *   admin_label = @Translation("GIT Events"),
 * )
 */
class GitEventsBlock extends BlockBase  implements BlockPluginInterface {
  
  /**
   * @param string
   * */
  private $username;
  
  /**
   * @param array
   * */
  private $config;
  
  /**
   * {@inheritdoc}
   */
  public function build() {
    
    $this->config = $this->getConfiguration();
    
    if (!empty($this->config['max'])) {
      $max = $this->config['max'];
    }
    else {
      $max = 10;
    }

    // check if the repository user name is set (if not then die)
    if(empty($this->config['username'])) {
      return array(
      '#markup' => '<p>Please set the GITHUB username in the block settings</p>',
        '#allowed_tags' => ['p']
      ); 
    }else{
      // set the user name
      $this->username = $this->config['username'];
    }
    
    // get the cached events (if exists)
    /**
     * @todo add this to the database
     * */
    $json = json_decode(@file_get_contents('modules/git_events/.events'));

    switch($this->config['update']) {
      case '1':
        $update = 'Y-m-d';
        break;
      case '2':
        $update = 'Y-m-d H';
        break;
      case '3':
        $update = 'Y-m-d H:i:s';
        break;
      default:
        $update = 'Y-m-d';
    }

    // we only want to update the events once a day (no need to spam Github)
    if(isset($json->timestamp)) {
      if(date('Y-m-d',strtotime('now')) != date($update,$json->timestamp))
        $json = $this->getLatestGitEvents();
    }else{
      $json = $this->getLatestGitEvents();  
    }

    // update the json file
    if(is_array($json)) {
      $json['timestamp'] = strtotime('now');
    }else{
      $json->timestamp = strtotime('now');
    }
    
    // try to set the cache
    if(false == @file_put_contents('modules/git_events/.events',json_encode($json)))
      return array(
        '#markup' => '<p>There was an issue writing the events cache file</p>',
        '#allowed_tags' => ['p']
      ); 
    
    // loop events and build
    $return = '<div class="git_events">';
      $i = 0; // counter
      foreach($json as $item) {
        if(isset($item->type)) {
          $i++;
          if($i >= $max)continue;
          switch($item->type) {
              case 'CreateEvent':
                $return = $this->CreateEvent($item,$return);
              break;
              case 'PushEvent':
                $return = $this->PushEvent($item,$return);
              break;
              case 'IssueCommentEvent':
                $return = $this->IssueCommentEvent($item,$return);
              break;
              case 'IssuesEvent':
                $return = $this->IssuesEvent($item,$return);
              break;
            default:
              continue;
          }
        }
      }
    $return .= '</div>';
    
    return array(
      '#markup' => $return,
      '#allowed_tags' => ['div','h3','4','a','p','ul','li']
    );
  }
  /**
   * @param object $item
   * @param string $return
   * @return string
   * */
  private function CreateEvent($item,$return)
  {
    if(empty($this->config['showcreate']))return $return;
    if(false === $this->config['showcreate'])return $return;
    
    $return = $this->eventHeader($return);
    $return .= '<div class="created">'.date('M j, Y h:i A T',strtotime($item->created_at)).'</div>';
    $return .= '<div>Project: <a href="'.$this->view_url($item->repo->url).'" target="_blank">'.$item->repo->name.'</div>';
    $return .= '<div>Description: '.substr($item->payload->description,0,150).'...</div>';
    $return = $this->eventFooter($item,$return);
    return $return;
  }
  /**
   * @param object $item
   * @param string $return
   * @return string
   * */
  private function PushEvent($item,$return)
  {
    if(empty($this->config['showcommits']))return $return;
    if(false === $this->config['showcommits'])return $return;
    
    $return = $this->eventHeader($return);
    $return .= '<div class="created">'.date('M j, Y h:i A T',strtotime($item->created_at)).'</div>';
    $return .= '<div>Project: '.$item->repo->name.'</div>';
    $return .= '<ul class="commits">';
    foreach($item->payload->commits as $c) {
      $return .= '<li>Commit: <a href="'.$this->view_url($c->url).'" target="_blank">'.$c->message.'</a></li>';  
    }
    $return .= '</ul>';
    $return = $this->eventFooter($item,$return);
    return $return;
  }
  /**
   * @param object $item
   * @param string $return
   * @return string
   * */
  private function IssueCommentEvent($item,$return)
  {
    
    if(empty($this->config['showissuecomments']))return $return;
    if(false === $this->config['showissuecomments'])return $return;
    
    $return = $this->eventHeader($return);
    $return .= '<div class="created">'.date('M j, Y h:i A T',strtotime($item->payload->issue->updated_at)).'</div>';
    $return .= '<div>Project: '.$item->repo->name.'</div>';
    $return .= '<div>Issue: <a href="'.$item->payload->issue->html_url.'" target="_blank">'.$item->payload->issue->title.'</a></div>';  
    $return .= '<div>Comment: '.substr($item->payload->comment->body,0,150).'...</div>';
    $return = $this->eventFooter($item,$return);
    return $return;
  }
  /**
   * @param object $item
   * @param string $return
   * @return string
   * */
  private function IssuesEvent($item,$return)
  {
    if(empty($this->config['showissue']))return $return;
    if(false === $this->config['showissue'])return $return;
    
    $return = $this->eventHeader($return);
    $return .= '<div class="created">'.date('M j, Y h:i A T',strtotime($item->payload->issue->updated_at)).'</div>';
    $return .= '<div>Project: '.$item->repo->name.'</div>';
    $return .= '<div>Issue: <a href="'.$item->payload->issue->html_url.'" target="_blank">'.$item->payload->issue->title.'</a></div>';  
    $return .= '<div>'.substr($item->payload->issue->body,0,150).'...</div>';
    $return = $this->eventFooter($item,$return);
    return $return;
  }
  /**
   * @param string $url
   * @return string
   * */
  private function view_url($url)
  {
    $return = str_replace('api.github.com','github.com',$url);
    $return = str_replace('/repos','',$return);
    $return = str_replace('/commits','/commit',$return);
    return $return;
  }
  /**
   * @param string $return
   * @return string
   * */
  private function eventHeader($return)
  {
    $return .= '<div class="git_event">';
    return $return;
  }
  /**
   * @param object $item
   * @param string $return
   * @return string
   * */
  private function eventFooter($item,$return)
  {
    $return .= '<div class="links">';
    $return .= '<a href="'.$this->view_url($item->repo->url).'" target="_blank" class="fltleft">VIEW PROJECT</a>';
    $return .= '</div><div class="clear"></div></div>';
    return $return;
  }
  /**
   * @return string
   * */
  private function getLatestGitEvents()
  {
    $header = "Accept:text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8
Accept-Language:en-US,en;q=0.8,es;q=0.6
Cache-Control:max-age=0
Connection:keep-alive
Cookie:_octo=GH1.1.982629611.1476975348; logged_in=yes; dotcom_user=061375; _ga=GA1.2.1066084491.1476975348
Host:api.github.com
Upgrade-Insecure-Requests:1
User-Agent:Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.95 Safari/537.36";
    $header = explode("\n",$header);
    $ch = curl_init(); 
    // set url 
    curl_setopt($ch, CURLOPT_URL, 'https://api.github.com/users/'.$this->username.'/events'); 
    //return the transfer as a string 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
    curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
    // $output contains the output string 
    $json = curl_exec($ch);
    curl_close($ch);
    return json_decode($json);
  }
  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    
    $config = $this->getConfiguration();
    
    $form = parent::blockForm($form, $form_state);

    $config = $this->getConfiguration();

    $form['gitevents_block'] = array (
      '#type' => 'textfield',
      '#title' => $this->t('Maximum posts per page'),
      '#description' => $this->t('How many posts should be displayed?'),
      '#default_value' => isset($config['max']) ? $config['max'] : '',
    );
    $form['gitevents_block_username'] = array (
      '#type' => 'textfield',
      '#title' => $this->t('User Name'),
      '#description' => $this->t('The GITHUB repository user name'),
      '#default_value' => isset($config['username']) ? $config['username'] : '',
    );
    $form['gitevents_block_showcreate'] = array (
      '#type' => 'checkbox',
      '#title' => $this->t('Display Create Event'),
      '#default_value' => isset($config['showcreate']) ? $config['showcreate'] : false,
    );
    $form['gitevents_block_showcommits'] = array (
      '#type' => 'checkbox',
      '#title' => $this->t('Display Commits'),
      '#default_value' => isset($config['showcommits']) ? $config['showcommits'] : false,
    );
    $form['gitevents_block_showissues'] = array (
      '#type' => 'checkbox',
      '#title' => $this->t('Display Issues'),
      '#default_value' => isset($config['showissues']) ? $config['showissues'] : false,
    );
    $form['gitevents_block_showissuecomments'] = array (
      '#type' => 'checkbox',
      '#title' => $this->t('Display Issue Comments'),
      '#default_value' => isset($config['showissuecomments']) ? $config['showissuecomments'] : false,
    );
    $form['gitevents_block_update'] = array (
      '#type' => 'select',
      '#title' => $this->t('How often should the cache be updated'),
      '#default_value' => (isset($config['update']) ? $config['update'] : 1),
      '#options' => array(
        '1' => $this->t('Once Daily'),
        '2' => $this->t('Once Hourly'),
        '3' => $this->t('Every Refresh')
      )
    );
    return $form;
  }
  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->setConfigurationValue('max', $form_state->getValue('gitevents_block'));
    $this->setConfigurationValue('username', $form_state->getValue('gitevents_block_username'));
    $this->setConfigurationValue('showcommits', $form_state->getValue('gitevents_block_showcommits'));
    $this->setConfigurationValue('showissues', $form_state->getValue('gitevents_block_showissues'));
    $this->setConfigurationValue('showcreate', $form_state->getValue('gitevents_block_showcreate'));
    $this->setConfigurationValue('showissuecomments', $form_state->getValue('gitevents_block_showissuecomments'));
    $this->setConfigurationValue('update', $form_state->getValue('gitevents_block_update'));
  }
  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $default_config = \Drupal::config('git_events.settings');
    return array(
      'max' => $default_config->get('gitevents_block.max')
    );
  }
}