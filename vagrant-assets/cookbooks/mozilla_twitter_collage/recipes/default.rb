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
package "imagemagick"
package "php5-dev"
package "php5-dbg"
package "php5-tidy"
package "php5-imagick"

execute "enable-modules" do
    command "sudo a2enmod actions expires deflate rewrite alias headers setenvif vhost_alias"
    notifies :reload, resources(:service => "apache2"), :delayed
end

execute "disable-default-site" do
    command "sudo a2dissite default"
    notifies :reload, resources(:service => "apache2"), :delayed
end

web_app "project" do
    template "project.conf.erb"
    notifies :reload, resources(:service => "apache2"), :delayed
end

template "/vagrant/config/config.yaml" do
    source "config.yaml.erb"
    owner "vagrant"
    group "vagrant"
    mode 0644
end

execute "sudo mkdir -m0777 -p /home/vagrant/logs /home/vagrant/data/twitterparty /home/vagrant/store/config /var/log/twitterparty;"
execute "/usr/bin/mysql -u root -p#{node[:mysql][:server_root_password]} < /vagrant/schema/db.sql;"
execute "/usr/bin/mysql -u root -p#{node[:mysql][:server_root_password]} twitterparty < /vagrant/schema/tables.sql;"

execute "cd /vagrant; php util/configure.php;"
execute "cd /vagrant; php util/mosaic-configure.php;"
#execute "cd /vagrant; php util/mosaic-make-tiles.php;"
