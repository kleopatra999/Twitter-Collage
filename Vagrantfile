Vagrant::Config.run do |config|

  config.vm.box = "lucid32"
  # Add to /etc/hosts: 192.168.10.30 dev.twitterparty.mozilla.com
  config.vm.network("192.168.10.30")

  config.vm.provision :chef_solo, :cookbooks_path => "vagrant-assets/cookbooks",
                                    :run_list => ["recipe[mozilla_twitter_collage]"]

end
