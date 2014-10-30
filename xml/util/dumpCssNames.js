use ( "XmlNamespaces" );
use ( "UnitTest" );
use ( "Log" );
use ( "XmlElements" );
use ( "XmlUtil" );

use("OpenFormatObject");
use("Print");

var marcx = XmlNamespaces.marcx;
var xmlObj = <collection xmlns:marcx="info:lc/xmlns/marcxchange-v1">\
<marcx:collection><marcx:record><dummy></dummy>\
</marcx:record></marcx:collection></collection>;

OpenFormatObject.init(xmlObj, "dan");
OpenFormatObject.initBibdkTags();
print(OpenFormatObject.printObjectNames("|"));
