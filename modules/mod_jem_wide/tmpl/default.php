<?php
/**
 * @version 4.0.1-dev1
 * @package JEM
 * @subpackage JEM Wide Module
 * @copyright (C) 2013-2023 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;


use Joomla\CMS\Language\Text;
// JHtml::_('behavior.modal', 'a.flyermodal');
?>

<div class="jemmodulewide<?php echo $params->get('moduleclass_sfx')?>" id="jemmodulewide">

<?php if (count($list)) : ?>
	<table class="eventset" summary="mod_jem_wide">

		<colgroup>
			<col width="30%" class="jemmodw_col_title" />
			<col width="20%" class="jemmodw_col_category" />
			<col width="20%" class="jemmodw_col_venue" />
			<col width="15%" class="jemmodw_col_eventimage" />
			<col width="15%" class="jemmodw_col_venueimage" />
		</colgroup>

		<?php foreach ($list as $item) : ?>
		<tr class="event_id<?php echo $item->eventid; ?>">
			<td valign="top">
				<?php if ($item->eventlink) : ?>
				<span class="event-title">
					<a href="<?php echo $item->eventlink; ?>" title="<?php echo $item->fulltitle; ?>"><?php echo $item->title; ?></a>
				</span>
				<?php else : ?>
				<span class="event-title">
					<?php echo $item->title; ?>
				</span>
				<?php endif; ?>
				<br />
				<span class="date" title="<?php echo strip_tags($item->dateinfo); ?>"><?php echo $item->date; ?></span>
				<?php
				if ($item->time && $params->get('datemethod', 1) == 1) :
				?>
				<span class="time" title="<?php echo strip_tags($item->dateinfo); ?>"><?php echo $item->time; ?></span>
				<?php endif; ?>
			</td>

			<td>
			<?php if (!empty($item->catname)) : ?>
				<span class="category"><?php echo $item->catname; ?></span>
			<?php endif; ?>
			</td>

			<td>
			<?php if (!empty($item->venue)) : ?>
				<?php if ($item->venuelink) : ?>
				<span class="venue-title"><a href="<?php echo $item->venuelink; ?>" title="<?php echo $item->venue; ?>"><?php echo $item->venue; ?></a></span>
				<?php else : ?>
				<span class="venue-title"><?php echo $item->venue; ?></span>
				<?php endif; ?>
			<?php endif; ?>
			</td>

			<td align="center" class="event-image-cell">
				<?php if ($params->get('use_modal')) : ?>
					<?php if ($item->eventimageorig) {
						$image = $item->eventimageorig;
					} else {
						$image = '';
					} ?>
				<a href="<?php echo $image; ?>" class="flyermodal" title="<?php echo $item->title; ?>">
				<?php endif; ?>
					<img src="<?php echo $item->eventimage; ?>" alt="<?php echo $item->title; ?>" class="image-preview" />
				<?php if ($item->eventlink) : ?>
				</a>
				<?php endif; ?>
			</td>

			<td align="center" class="event-image-cell">
				<?php if ($params->get('use_modal')) : ?>
				<a href="<?php echo $item->venueimageorig; ?>" class="flyermodal" title="<?php echo $item->venue; ?>">
				<?php endif; ?>
					<img src="<?php echo $item->venueimage; ?>" alt="<?php echo $item->venue; ?>" class="image-preview" />
				<?php if ($item->venuelink) : ?>
				</a>
				<?php endif; ?>
			</td>
		</tr>
		<?php endforeach; ?>
	</table>
<?php else : ?>
	<?php echo Text::_('MOD_JEM_WIDE_NO_EVENTS'); ?>
<?php endif; ?>
</div>
