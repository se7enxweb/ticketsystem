{* A column header that sorts the staff desk (view parameters /(sort)/<key>/(order)/asc|desc). Input: $key, $text,
   $cls (optional), and the caller's $sort_urls, $sort, $order. The part's own variables carry the ts prefix (an
   include shares its caller's namespace). *}
{if is_set( $sort_urls[$key] )}
<th scope="col"{if is_set( $cls )} class="{$cls|wash}"{/if} aria-sort="{if $sort|eq( $key )}{if $order|eq( 'asc' )}ascending{else}descending{/if}{else}none{/if}"><a data-sort-key="{$key|wash}" href={$sort_urls[$key]|ezurl}>{$text|wash}{if $sort|eq( $key )} <span aria-hidden="true">{if $order|eq( 'asc' )}&#9650;{else}&#9660;{/if}</span>{/if}</a></th>
{else}
<th scope="col"{if is_set( $cls )} class="{$cls|wash}"{/if}>{$text|wash}</th>
{/if}
