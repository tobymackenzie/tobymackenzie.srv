#!/bin/bash
#==add admin user
if ! id -u 2b > /dev/null 2>&1; then
	echo 'adding 2b user'
	useradd --create-home --shell /bin/bash 2b
	echo '2b ALL=(ALL) NOPASSWD: ALL' >> /etc/sudoers
	visudo -c
	mkdir -p /home/2b/.ssh
	if [ -f /home/vagrant/.ssh/authorized_keys ]; then
		cp /home/vagrant/.ssh/authorized_keys /home/2b/.ssh/authorized_keys
	else
		cp /root/.ssh/authorized_keys /home/2b/.ssh/authorized_keys
	fi
	chown -R 2b:2b /home/2b/.ssh
	chmod 0700 /home/2b/.ssh
	chmod 0600 /home/2b/.ssh/*
fi
