#!/usr/bin/env bash

"""
Generate data for request to openformat webservice.
open given file - stream edit to insert given display

@params $1 name of file
@params $2 the display to use
@returns given xml-file with outputFormat set to given display
"""
generate_post_data(){
cat <<EOF
$(sed  "s/^\s*<ns1:outputFormat>.*/<ns1:outputFormat>"$2"<\/ns1:outputFormat>/g" "$1")
EOF
}

URL=http://php-openformat-prod.mcp1-proxy.dbc.dk/server.php
FILES=request/*.xml

for DISPLAY in "netpunkt_brief" "netpunkt_standard" "netpunkt_dm2" "netpunkt_marc21" "bob_detail"
    do
        COUNTER=1
        for FILENAME in $FILES
            do
              COUNTER=$[$COUNTER +1]
              INPUT=$(generate_post_data "$FILENAME" "$DISPLAY")
              # TODO user real openformat
              RESULT=$(curl -X POST --data "$INPUT" "$URL")
                if [ "$COUNTER" -gt 100 ]
                    then
                        break
                fi
            done
    done