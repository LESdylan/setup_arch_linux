- [] we install the machine in sgoinfre and the debian *.iso too.
- [] set the RAM memory
- [] Create a virtual disk
- [] we set the hard disk size of 30GB
- [] We attach the iso to virtual box
# Next round configure the installer
- [] the language of the system
- [] set the country where we live
- [] select the continent
- [] the country : spain
- [] if combination of language not good. we will need to set teh base default locale settings on: en_US.UTF-8
- [] keymap follow the ANSI standard so American english but in our case, we can set spanish, it rooughly change nothing.
## configure the network 
- [] hostname : dlesieur
- [] domain name : ""
## Set up users and passwords
- [] set password for root user
- [] set name of user : dlesieur
- [] set password of user
## Configure the clock
- [] location in time zone : Madrid

## Bonus: partition disks
- [] we select manual disk partitioning
- [] we create a new partition and we create our primary partition
- [] partition of 500MiB
- [] partition primary at the beginning
- [] now we modify the mount point to /boo
- [] extended log, create a new partition
- [] new partition max size logical we don't mount it
- [] we take the logical partition as the root for all the future logic volumes /dev/sda5
- [] sda1 500M 0 part /boot
- [] sda5_crypt 30.3G 0 crypt
- [] LVMGroup-root 10G 0 lvm /
- [] LVMGroup-swap 2.3G 0 lvm [SWAP]
- [] LVMGroup-home 5G 0 lvm /home
- [] LVMGroup-var 3G 0 lvm /var
- [] LVMGroup-srv 3G 0 lvm /src
- [] LVMGroup-tmp 3G 0 lvm /tmp
- [] LVMGroup-var--log 4G 0 lvm /var/log
- [] For each one of them we need ot configure the file system and the mount point
- [] package manager : no
- [] country : spain
- [] deb.debian.org  for the debian archive mirror
- [] boolean no http://[user][:pass][\host][:port]/
- []  no to popularity constest
- [] left blank all type of software choices (with the space bar)
- [] we install the GRUB, we say yes and the device is in /dev/sda


- select and install software stage
- installing grub boot loader
- finishing installation
- 
