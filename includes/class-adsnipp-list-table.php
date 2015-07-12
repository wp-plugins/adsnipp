<?php

/**
 * Description of adsnipp-list-table
 * Class displays ads records in nice looking table
 */

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Adsnipp_List_Table extends WP_List_Table
{
    function __construct()
    {
        global $status, $page;

        parent::__construct(array(
            'singular' => 'ad',
            'plural' => 'ads',
        ));
    }

    function column_default($item, $column_name)
    {
        return $item[$column_name];
    }

    function column_published($item)
    {
        return ($item['published'] ? '<span style="color: green;">On</span>' : '<span style="color: red;">Off</span>');
    }

    function column_impressions($item)
    {
        return adsnipp_count_impression($item['id']);
    }

    function column_clicks($item)
    {
        return adsnipp_count_clicks($item['id']);
    }

    function column_title($item)
    {
        $actions = array(
            'edit' => sprintf('<a href="?page=adsnipp_ads&view=ad_form&id=%s">%s</a>', $item['id'], __('Edit', 'adsnipp')),
            'delete' => sprintf('<a href="?page=%s&action=delete&id=%s">%s</a>', $_REQUEST['page'], $item['id'], __('Delete', 'adsnipp')),
        );

        return sprintf('%s %s',
            $item['title'],
            $this->row_actions($actions)
        );
    }

    function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="id[]" value="%s" />',
            $item['id']
        );
    }

    function get_columns()
    {
        $columns = array(
            'cb'			=> '<input type="checkbox" />',
            'title'			=> __('Title', 'adsnipp'),
            'network'		=> __('Ad Network', 'adsnipp'),
            'platform'		=> __('Platform', 'adsnipp'),
			'impressions'	=> __('Impressions', 'adsnipp'),
			'clicks'		=> __('Clicks', 'adsnipp'),
			'published'		=> __('Status', 'adsnipp'),
        );
        return $columns;
    }

    function get_sortable_columns()
    {
        $sortable_columns = array(
            'title'			=> array('title', true),
            'platform'		=> array('platform', false),
            /*'impressions'	=> array('impressions', false),
			'clicks'		=> array('clicks', false),*/
        );
        return $sortable_columns;
    }

    function get_bulk_actions()
    {
        $actions = array(
			'on'		=> 'On',
			'off'		=> 'Off',
            'delete'	=> 'Delete',
        );
        return $actions;
    }

	function process_bulk_action()
	{
        global $wpdb;
        $table_name = $wpdb->prefix . 'adsnipp_ads';

		$ids = isset($_REQUEST['id']) ? $_REQUEST['id'] : array();
		if (is_array($ids)) {
			$ids = implode(',', $ids);
		}

		if (!empty($ids)) {
			if ('on' === $this->current_action()) {
				$wpdb->query("UPDATE $table_name SET published = 1 WHERE id IN($ids)");

			} else if ('off' === $this->current_action()) {
				$wpdb->query("UPDATE $table_name SET published = 0 WHERE id IN($ids)");

			} else if ('delete' === $this->current_action()) {
				$wpdb->query("DELETE FROM $table_name WHERE id IN($ids)");
				$wpdb->query("DELETE FROM {$wpdb->prefix}adsnipp_stats WHERE ad_id IN($ids)");
			}
		}
    }

	function prepare_items()
	{
		global $wpdb;
		$table_name = $wpdb->prefix . 'adsnipp_ads';

		$per_page = 5;

		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array($columns, $hidden, $sortable);

		$this->process_bulk_action();

		$total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name");

		$paged		= isset($_REQUEST['paged']) ? max(0, intval($_REQUEST['paged']) - 1) : 0;
		$orderby	= (isset($_REQUEST['orderby']) && in_array($_REQUEST['orderby'], array_keys($this->get_sortable_columns()))) ? $_REQUEST['orderby'] : 'title';
		$order		= (isset($_REQUEST['order']) && in_array($_REQUEST['order'], array('asc', 'desc'))) ? $_REQUEST['order'] : 'asc';

        $this->items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name ORDER BY $orderby $order LIMIT %d OFFSET %d", $per_page, $paged), ARRAY_A);

		$this->set_pagination_args(array(
			'total_items'	=> $total_items,
			'per_page'		=> $per_page,
			'total_pages'	=> ceil($total_items / $per_page)
		));
    }
}