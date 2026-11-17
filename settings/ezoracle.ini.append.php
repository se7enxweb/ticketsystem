<?php /* #?ini charset="utf-8"?

# Oracle (the ezoracle extension): ticketsystem_ticket has a column named "number", an Oracle reserved word and
# data type. The schema handler creates it quoted ("NUMBER"); listed here, the driver quotes it in every SELECT,
# INSERT, UPDATE and DELETE, so eZPersistentObject and the queries of the help desk stay as on every other database.
# Needs an ezoracle that knows [QuotedIdentifierSettings] TypeColumnNames. Other databases ignore this file.
[QuotedIdentifierSettings]
TypeColumnNames[]=number

*/ ?>
