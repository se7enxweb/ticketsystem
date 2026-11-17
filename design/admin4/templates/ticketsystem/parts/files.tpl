{* The files of a post or a note (ticketsystemFiles::byPost()). Input: $files (id, name, size_label). Downloads go
   through ticketsystem/file, which checks the reader again. *}
<ul class="a4-badges" aria-label="{'Attachments'|i18n( 'extension/ticketsystem' )|wash}">
{foreach $files as $tsFile}<li><a class="a4-tag" href={concat( 'ticketsystem/file/', $tsFile.id )|ezurl} download="">{$tsFile.name|wash} <span class="a4-meta">{$tsFile.size_label|wash}</span></a></li>{/foreach}
</ul>
