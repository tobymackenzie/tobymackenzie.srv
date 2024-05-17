Vagrant.require_version '>= 1.7.0'

Vagrant.configure(2) do |config|
	config.vm.define 'web' do |web|
		web.vm.box = 'ubuntu/focal64'

		#==network
		#-# virtualbox security change limits IP available
		web.vm.network 'private_network', ip: '192.168.56.6'
		#--connect to internet
		#-@ https://stackoverflow.com/a/18457420/1139122
		web.vm.provider 'virtualbox' do |vb|
			vb.customize ['modifyvm', :id, '--natdnshostresolver1', 'on']
			vb.customize ['modifyvm', :id, '--natdnsproxy1', 'on']
		end

		#==provision
		web.vm.provision 'shell', path: 'provision/init.sh', privileged: true
		#-@ [official](http://docs.ansible.com/ansible/guide_vagrant.html)
		web.vm.provision 'ansible' do |ansible|
			ansible.verbose = 'v'
			ansible.playbook = 'provision/dev.yml'
		end

		#==sync local folders
		#--sync sites folders
		web.vm.synced_folder './sites/10k-gol.site', '/var/www/sites/10k-gol.site', owner: '2b', group: '2b'
		web.vm.synced_folder './sites/dev.tobymackenzie.com', '/var/www/sites/dev.tobymackenzie.com', owner: '2b', group: '2b'
		web.vm.synced_folder './sites/dw', '/var/www/sites/dw', owner: '2b', group: '2b'
		web.vm.synced_folder './sites/tmprivate', '/var/www/sites/tmprivate', owner: '2b', group: '2b'
		web.vm.synced_folder './sites/tobymackenzie.com', '/var/www/sites/tobymackenzie.com', owner: '2b', group: '2b'

		#--disable syncing project folder
		web.vm.synced_folder '.', '/vagrant', disabled: true
	end
	config.vm.define 'build', autostart: false do |build|
		build.vm.box = 'ubuntu/focal64'

		#==network
		build.vm.network 'private_network', ip: '192.168.56.7'
		#--connect to internet
		#-@ https://stackoverflow.com/a/18457420/1139122
		build.vm.provider 'virtualbox' do |vb|
			vb.customize ['modifyvm', :id, '--natdnshostresolver1', 'on']
			vb.customize ['modifyvm', :id, '--natdnsproxy1', 'on']
		end

		#==provision
		build.vm.provision 'shell', path: 'provision/init.sh', privileged: true
		#-@ [official](http://docs.ansible.com/ansible/guide_vagrant.html)
		build.vm.provision 'ansible' do |ansible|
			ansible.verbose = 'v'
			ansible.playbook = 'provision/build.yml'
		end

		#==sync local folders
		#--sync sites folders
		build.vm.synced_folder './sites/10k-gol.site', '/var/www/sites/10k-gol.site', owner: '2b', group: '2b'
		build.vm.synced_folder './sites/dev.tobymackenzie.com', '/var/www/sites/dev.tobymackenzie.com', owner: '2b', group: '2b'
		build.vm.synced_folder './sites/dw', '/var/www/sites/dw', owner: '2b', group: '2b'
		build.vm.synced_folder './sites/tmprivate', '/var/www/sites/tmprivate', owner: '2b', group: '2b'
		build.vm.synced_folder './sites/tobymackenzie.com', '/var/www/sites/tobymackenzie.com', owner: '2b', group: '2b'

		#--disable syncing project folder
		build.vm.synced_folder '.', '/vagrant', disabled: true
	end
	config.vm.define 'deploy', autostart: false do |deploy|
		deploy.vm.box = 'ubuntu/focal64'

		#==network
		#-# virtualbox security change limits IP available
		deploy.vm.network 'private_network', ip: '192.168.56.8'
		#--connect to internet
		#-@ https://stackoverflow.com/a/18457420/1139122
		deploy.vm.provider 'virtualbox' do |vb|
			vb.customize ['modifyvm', :id, '--natdnshostresolver1', 'on']
			vb.customize ['modifyvm', :id, '--natdnsproxy1', 'on']
		end

		#==provision
		deploy.vm.provision 'shell', path: 'provision/init.sh', privileged: true
		#-@ [official](http://docs.ansible.com/ansible/guide_vagrant.html)
		deploy.vm.provision 'ansible' do |ansible|
			ansible.verbose = 'v'
			ansible.playbook = 'provision/dev.deploy.yml'
		end

		#==sync local folders
		#--disable syncing project folder
		deploy.vm.synced_folder '.', '/vagrant', disabled: true
	end
end
