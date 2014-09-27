launchat
========

Lightweight CLI launcher that drops into your ATLauncher folder to launch packs without the GUI.

Run with:

`php launchat.php InstanceName`

Where InstanceName is the name of a folder in Instances/.

On the first run (or if refreshing your access token fails), it will ask for your username (Mojang e-mail) 
and password. The password is never stored -- all data is inside of launchat.json, which is placed in the
same folder as the launcher script (the ATLauncher root).

This is a "works for me" kind of thing, where I'm playing 1.7 packs on OSX. It may work on other versions
or on Linux, but it is not tested. There's almost no chance of it working on Windows. The main reason this
was written was because ATLauncher is a very RAM-hungry app, at least on OSX, so launching with this can
save your computer a couple hundred MB of RAM.

If anyone wants to fork this and make it work on more operating systems, Minecraft versions, or if you
just hate PHP and want to implement this in more languages, please go ahead!
