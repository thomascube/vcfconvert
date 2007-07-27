vCard to LDIF/CSV Converter
===========================
by Thomas Bruederli

To run this converter just copy all files to a webserver directory where PHP
is installed and enabled. Open your browser and type in the URL of your
webserver with the according folder. By default, file uploads up to 2MB are 
allowed.

Comandline version
------------------
This package also includes a shell script to invoke the converter from the
command line. PHP is also required to be installed on your machine.
Just copy the files anywhere on your disk, open a terminal and type the
following commands:

> cd /path/to/vcfconvert
> ./convert -f ldif -o destination_file.ldif source_file.vcf
or
> ./convert -hv -f csv -d ";" -o destination_file.csv source_file.vcf

To get information about optinal parameters, type
> ./convert help

This script is licensed under the GNU GPL a copy of which has been provided.
Copies of this license can also be found at
http://www.fsf.org/licensing/licenses/gpl.txt

For any bug reports or feature requests please visit my website 
http://labs.brotherli.ch or send a message to endless@brotherli.ch


** Note from Kevin on libdlusb compatibility **
Due to the fact libdlusb is incapable of transmitting all the information
generally used with the contact application (currently it is capable of 
only transmitting name, type, and phone number) I have intentionally organized
the format to be convenient for saving into the note application instead. This 
allows the user to have multiple phone numbers per entry along with an e-mail
address.

