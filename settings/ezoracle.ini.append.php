<?php /* #?ini charset="utf-8"?

# Oracle (the ezoracle extension): two columns of the help desk have names that are Oracle reserved words:
#   number  ticketsystem_ticket (also an Oracle data type)
#   level   ticketsystem_escalation (the pseudocolumn of CONNECT BY; unquoted it fails with ORA-01788 or ORA-03050)
# The schema handler creates them quoted ("NUMBER", "LEVEL"); listed here, the driver quotes them in every SELECT,
# INSERT, UPDATE and DELETE, so eZPersistentObject and the queries of the help desk stay as on every other database.
# Needs an ezoracle that knows [QuotedIdentifierSettings] TypeColumnNames. Other databases ignore this file.
[QuotedIdentifierSettings]
TypeColumnNames[]=number
TypeColumnNames[]=level

*/ ?>
