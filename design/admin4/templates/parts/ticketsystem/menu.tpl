{* The left menu of the tab TicketSystem in admin4 and admin4l (navigation part ezticketsystemnavigationpart,
   doc/views.md): menu.ini [Leftmenu_ticketsystem] drawn by parts/ini_menu.tpl, the lists and the management pages
   as groups, each link by its policy, the current page marked as in every other admin4 section; then the width
   switch every admin4 left menu ends with. The import link only when [ImportSettings] Enabled=enabled. The classic
   design/admin draws its own list (design/admin/templates/parts/ticketsystem/menu.tpl). *}
{def $tsI = 'extension/ticketsystem'}
{* uri_string: the address with its view parameters, so the lists below ticketsystem/list are marked too (the
   kernel's $uri_string leaves them out) *}
{include uri='design:parts/ini_menu.tpl' ini_section='Leftmenu_ticketsystem' uri_string=$requested_uri_string|trim( '/' )
         hidden_links=cond( ezini( 'ImportSettings', 'Enabled', 'ticketsystem.ini' )|eq( 'enabled' ), array(), array( 'import' ) )
         i18n_hash=hash(
    'ticketsystem', 'TicketSystem'|i18n( $tsI ),
    'active',     'Not resolved'|i18n( $tsI ),
    'staff',      'Needs a staff reply'|i18n( $tsI ),
    'mine',       'Assigned to me'|i18n( $tsI ),
    'unassigned', 'Unassigned'|i18n( $tsI ),
    'urgent',     'Urgent'|i18n( $tsI ),
    'escalated',  'Escalated'|i18n( $tsI ),
    'done',       'Resolved or closed'|i18n( $tsI ),
    'all',        'All tickets'|i18n( $tsI ),
    'new',        'New ticket'|i18n( $tsI ),
    'reports',    'Reports'|i18n( $tsI ),
    'search',     'Search'|i18n( $tsI ),
    'canned',     'Canned replies'|i18n( $tsI ),
    'sla',        'SLA and escalation'|i18n( $tsI ),
    'satisfaction', 'Satisfaction'|i18n( $tsI ),
    'queues',     'Queues and categories'|i18n( $tsI ),
    'mail',       'Mail, files and ratings'|i18n( $tsI ),
    'import',     'Import (WHMCS)'|i18n( $tsI ),
)}
{undef $tsI}

{* Left menu width control. *}
{include uri='design:parts/left_width_control.tpl'}
