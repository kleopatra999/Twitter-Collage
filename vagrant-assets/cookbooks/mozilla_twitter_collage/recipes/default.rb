require_recipe "apt"

require_recipe "apache2"
require_recipe "openssl"
require_recipe "memcached"
require_recipe "mysql"
require_recipe "mysql::server"
require_recipe "php::php5"
require_recipe "php::pear"
require_recipe "php::module_mysql"
require_recipe "php::module_memcache"
require_recipe "php::module_curl"

package "curl"
package "php5-dev"
#package "php5-mcrypt"

template "/vagrant/config/config.yaml" do
    source "config.yaml.erb"
    owner "vagrant"
    group "vagrant"
    mode 0644
end

execute "disable-default-site" do
    command "sudo a2dissite default"
    notifies :reload, resources(:service => "apache2"), :delayed
end

execute "enable-modules" do
    command "sudo a2enmod actions expires deflate rewrite alias headers setenvif vhost_alias"
    notifies :reload, resources(:service => "apache2"), :delayed
end

execute "sudo mkdir /home/vagrant/logs; sudo chmod a+rw -R /home/vagrant/logs"
execute "sudo mkdir /home/vagrant/data; sudo chmod a+rw -R /home/vagrant/data"
execute "sudo mkdir /var/log/twitterparty; sudo chmod 777 /var/log/twitterparty"
execute "/usr/bin/mysql -u root -p#{node[:mysql][:server_root_password]} < /vagrant/schema/db.sql;"

execute "/usr/bin/mysql -u root -p#{node[:mysql][:server_root_password]} twitterparty < /vagrant/schema/tables.sql;"

execute "cd /vagrant; php util/configure.php;"

web_app "project" do
    template "project.conf.erb"
    notifies :reload, resources(:service => "apache2"), :delayed
end
