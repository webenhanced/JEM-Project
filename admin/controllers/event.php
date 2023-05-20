<?php
/**
 * @version 4.0b3
 * @package JEM
 * @copyright (C) 2013-2023 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 *
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

require_once (JPATH_COMPONENT_SITE.'/classes/controller.form.class.php');

/**
 * JEM Component Event Controller
 *
*/
class JemControllerEvent extends JemControllerForm
{
	/**
	 * @var    string  The prefix to use with controller messages.
	 *
	 */
	protected $text_prefix = 'COM_JEM_EVENT';


	/**
	 * Constructor.
	 *
	 * @param  array $config  An optional associative array of configuration settings.
	 * @see    JController
	 *
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);
	}

	/**
	 * Function that allows child controller access to model data
	 * after the data has been saved.
	 * Here used to trigger the jem plugins, mainly the mailer.
	 *
	 * @param   JModel(Legacy)  $model      The data model object.
	 * @param   array           $validData  The validated data.
	 *
	 * @return  void
	 *
	 * @note    On J! 2.5 first param is 'JModel &$model' but
	 *          on J! 3.x it's 'JModelLegacy $model'
	 *          one of the bad things making extension developer's life hard.
	 */
	protected function _postSaveHook($model, $validData = array())
	{
		$isNew = $model->getState('event.new');
		$id    = $model->getState('event.id');

		// trigger all jem plugins
		JPluginHelper::importPlugin('jem');
		$dispatcher = JemFactory::getDispatcher();
		$dispatcher->triggerEvent('onEventEdited', array($id, $isNew));

		// but show warning if mailer is disabled
		if (!JPluginHelper::isEnabled('jem', 'mailer')) {
			Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_GLOBAL_MAILERPLUGIN_DISABLED'), 'notice');
		}
	}
}
