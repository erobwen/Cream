# Cream
An advanced ORM supporting real OOP working as a plugin on top of CakePHP 2. It supports single and multi table inheritance, and gives a lot of features with no additional costs.  

See included file Cream.pptx for an overview of the features of Cream.

# Compatibility:
Cream is developed for 2.7.0 RC and for 2.4.6, so we may assume that it it is compatible with Cake versions in between. 

# Requirements: 
You have to add Containable behaviour in your AppModel for Cream to work. 

# Deployment:
This git repository is an installation of CakePHO 2.7.0 RC, that way it is easier to clone the repository and try Cream out without tedious setup. 
However! the only essential file for Cream is Entity.php that resides in Model/Entity/ directory, so if you want cream in your project, simply copy this file into your project. See requirements for additional requirements. (to date, the only additional requirement is that you need to add Containable behaviour in your AppModel)

# Demonstration
In order to try out Cream, you can run actions in the DemoController, for example by url "http://localhost/cream/demo/multi_table". However, currently many of the actions are commented out, since they dependend on models from the project where Cream was originally created. In the future these demo actions will be updated.  
