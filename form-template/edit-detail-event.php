<?php /* @var $this LWP_Event */ /* @var $post object */ ?>
<?php wp_nonce_field('lwp_event_detail', '_lwpeventnonce'); ?>

<h4><?php $this->e('Ticket Sale Setting');?></h4>
<table class="form-table" id="lwp-event-setting">
	<tbody>
		<tr>
			<th valign="top">
				<label for="event_selling_limit"><?php $this->e('Limit to sell');  ?></label>
			</th>
			<td>
				<input placeholder="ex. <?php echo date_i18n('Y-m-d'); ?>" type="text" id="event_selling_limit" name="event_selling_limit" class="date-picker" value="<?php echo esc_attr(get_post_meta($post->ID, $this->meta_selling_limit, true)); ?>" />
				<span class="description">YYYY-MM-DD</span>
			</td>
		</tr>
		<tr>
			<th valign="top"><label><?php $this->e('Limit to cancel'); ?></label></th>
			<td>
				<?php $limits = get_post_meta($post->ID, $this->meta_cancel_limits, true); ?>
				<ul id="cancel-date-list"<?php if(empty($limits)) echo 'class="zero"'; ?>>
					<?php if(!empty($limits)) foreach($limits as $limit): ?>
						<li><?php printf(
							$this->_('Cacnelable till %1$s days before, %2$s %'),
							'<input type="text" class="small-text" readonly="readonly" name="cancel_limit_day[]" value="'.$limit['days'].'" />',
							'<input type="text" class="small-text" readonly="readonly" name="cancel_limit_ratio[]" value="'. $limit['ratio'] .'" />'
						);?><a class="button" href="#"><?php $this->e('Delete'); ?></a></li>
					<?php endforeach; ?>
					<li class="no-cancelable-date"><?php $this->e('You do not specify cancel limit, so user cannot cancel.'); ?></li>
				</ul>
				<p>
					<?php
						printf(
							$this->_('User can cancel ticket %1$s days before selling limit. Refund is %2$s%%.'),
							'<input type="text" name="cancel_limit" class="small-text" value="" />',
							'<input type="text" name="cancel_ratio" class="small-text" value="" />'
						);
					?>
					<a href="#" id="lwp-cancel-add" class="button"><?php $this->e('Add'); ?></a>
				</p>
				<p class="description"><?php $this->e('If you specify cancelable date, user can cancel transaction after payment.'); ?></p>
			</td>
		</tr>
	</tbody>
</table>

<h4><?php $this->e('Ticket List');?></h4>
<table class="form-table" id="ticket-list-table">
	<thead>
		<tr>
			<th scope="col"><?php $this->e('Name'); ?></th>
			<th scope="col"><?php $this->e('Description'); ?></th>
			<th scope="col"><?php $this->e('Price'); echo ' <small>('.lwp_currency_code().')</small>'; ?></th>
			<th scope="col"><?php $this->e('Stock'); ?></th>
			<th scope="col">&nbsp;</th>
			<th scope="col">&nbsp;</th>
		</tr>
	</thead>
	<tbody>
		<?php $tickets = new WP_Query('post_type='.$this->post_type.'&post_parent='.$post->ID); if($tickets->have_posts()): while($tickets->have_posts()): $tickets->the_post(); ?>
		<tr id="ticket-<?php the_ID(); ?>">
			<th scope="row"><?php the_title(); ?></th>
			<td><?php echo mb_substr(strip_tags(get_the_excerpt()), 0, 10, 'utf-8'); ?>&hellip;</td>
			<td><?php echo number_format(get_post_meta(get_the_ID(), 'lwp_price', true)); ?></td>
			<td><?php echo number_format(get_post_meta(get_the_ID(), $this->meta_stock, true)); ?></td>
			<td>
				<a href="#" class="button ticket-edit"><?php $this->e('Edit'); ?></a>
			</td>
			<td>
				<a href="<?php echo admin_url('admin-ajax.php'); ?>" class="button ticket-delete"><?php $this->e('Delete'); ?></a>
			</td>
		</tr>
		<?php endwhile; endif; ?>
	</tbody>
</table>

<h4><?php $this->e('Ticket form'); ?></h4>
<input type="hidden" name="ticket_id" value="0" />
<input type="hidden" name="ticket_parent_id" id="ticket_parent_id" value="<?php echo $post->ID; ?>" />
<table class="form-table" id="lwp-event-edit-form">
	<tbody>
		<tr>
			<th valign="top"><label for="ticket_post_title"><?php $this->e('Ticket Name'); ?></label></th>
			<td>
				<input type="text" name="ticket_post_title" id="ticket_post_title" class="regular-text" value="" />
				<p class="description ticket-status">
					<span class="newly-add"><?php $this->e('Added as new ticket.');  ?></span>
					<span class="editting"><?php $this->e('Editting existing ticket.');  ?></span>
				</p>
			</td>
		</tr>
		<tr>
			<th valign="top"><label for="ticket_post_content"><?php $this->e('Ticket Description'); ?></label></th>
			<td>
				<textarea rows="5" type="text" name="ticket_post_content" id="ticket_post_content"></textarea>
			</td>
		</tr>
		<tr>
			<th valign="top"><label for="ticket_price"><?php $this->e('Ticket Price'); ?></label></th>
			<td>
				<input type="text" name="ticket_price" id="ticket_price" value="" /><?php echo lwp_currency_code();?>
			</td>
		</tr>
		<tr>
			<th valign="top"><label for="ticket_stock"><?php $this->e('Ticket Stock'); ?></label></th>
			<td>
				<input type="text" name="ticket_stock" id="ticket_stock" value="" />
			</td>
		</tr>
	</tbody>
</table>
<p class="submit">
	<a id="ticket_cancel" class="button" href="#"><?php $this->e('Clear form'); ?></a>
	<a id="ticket_submit" class="button-primary" href="<?php echo admin_url('admin-ajax.php'); ?>"><?php $this->e('Edit ticket'); ?></a>
</p>