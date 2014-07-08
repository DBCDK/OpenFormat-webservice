# read the downloadet search result and wrap it in
# a OpenFormat request template
# ajust defaut namespace
# usage:
# python reformatNewRequestRecord.py TemplateFileName SearchResponseFileName
#     TemplateFileName file with format request template
#     SearchResponseFileName file with response from search

import re
import sys

tfilename = sys.argv[1]
ffilename = sys.argv[2]

t = open(tfilename)
templatelines = [linet.strip() for linet in t.readlines()]
t.close();

f = open(ffilename)
flines = [linef.strip() for linef in f.readlines()]
f.close()

fundet=False
# use the template file as - template
for templateline in templatelines:
    # find the line before and after object
    if templateline.rfind('<os:object') >= 0: fundet=True
    if templateline.rfind('<\/os:object') >= 0: fundet=False
    
    # insert the search result object here
    if fundet: 
        tfundet=False
        fundet=False
        rfundet=False
        pending=False
        # skip the line before object starts
        # and the lines after
        for fline in flines:
            if fline.rfind('<object') >= 0: tfundet=True
            if fline.rfind('</object') >= 0: tfundet=False

            ## relations start the things all over again
            ## test for the out relation-tags
            if fline.rfind('<relation') >= 0: rfundet=True
            if fline.rfind('</relation') >= 0: rfundet=True

            ## allways output a ending object tag before relationObject
            ## it is eaten by the test for ending object before this
            if fline.rfind('</relationObject') >= 0: print '</os:object>'

            if tfundet or rfundet: 
                # reset the relation mark the actual one will be output here
                # not the most elegant code - but it works!
                rfundet = False
                # check if namespace is in place
                matchObj = re.match(r'<[^:>]+:|</[^:>]+:', fline)
                if matchObj:
                    print fline
                else:
                    # namespace missing, ie. default, insert os
                    matchObj = re.match(r'^<[^/]', fline)
                    # this is a start tag (and optional end tag on same line)
                    if matchObj:
                        res = re.sub(r'^<', "<os:", fline)
                        res = re.sub(r'</', "</os:", res)
                    else:
                        # it is only an endtag
                        res = re.sub(r'</', "</os:", fline)
                    print res
    else:
        # output lines before and after object-line in template
        # insert the filename at placeholder in trackingId line
        templateline = templateline.replace('FILENAMEPLACE', ffilename)
        print templateline
#end
