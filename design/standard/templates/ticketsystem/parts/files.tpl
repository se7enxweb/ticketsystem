{* The files of a post or a note (ticketsystemFiles::byPost()). Input: $files (id, name, size_label). Downloads go
   through ticketsystem/file, which checks the reader again. *}
<ul class="ktl-chips ts-files" aria-label="{'Attachments'|i18n( 'extension/ticketsystem' )|wash}">
{foreach $files as $tsFile}<li><a class="ktl-chip ts-file" href={concat( 'ticketsystem/file/', $tsFile.id )|ezurl} download=""><i class="fa-solid fa-paperclip" aria-hidden="true"></i> {$tsFile.name|wash} <span class="ktl-muted ts-muted">{$tsFile.size_label|wash}</span></a></li>{/foreach}
</ul>
