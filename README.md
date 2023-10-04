<p align="center">
    <a href=https://github.com/Terpz710/iProtector/tree/master"><img src="https://github.com/Terpz710/iProtector/blob/master/icon.png"></img></a><br>
    <b>iProtector plugin for Pocketmine-MP</b>

# Info

iProtector is a simple area protection plugin for server owners and admins to protect their builds and/or minigames.

Please Note: This plugin is not intended to be used by players to protect their own houses.

# Commands/Permission

```
Commands:

/area <pos1/pos2/create/flag/list/delete>

Permissions:

iprotector:
    description: "Allows access to all iProtector features."
    default: false
    children:
      iprotector.access:
        description: "Allows access to editing terrain in iProtector areas."
        default: op
      iprotector.command:
        description: "Allows access to all iProtector commands."
        default: false
        children:
          iprotector.command.area:
            description: "Allows access to the area command."
            default: op
            children:
              iprotector.command.area.pos1:
                description: "Allows access to the iProtector pos1 subcommand."
                default: op
              iprotector.command.area.pos2:
                description: "Allows access to the iProtector pos2 subcommand."
                default: op
              iprotector.command.area.create:
                description: "Allows access to the iProtector create subcommand."
                default: op
              iprotector.command.area.list:
                description: "Allows access to the iProtector list subcommand."
                default: op
              iprotector.command.area.flag:
                description: "Allows access to the iProtector flag subcommand."
                default: op
              iprotector.command.area.delete:
                description: "Allows access to the iProtector delete subcommand."
                default: op
```

# Credits

Big thank you to @LDX the original creator of iProtector!

Great thanks to @MegaSamNinja for making this awesome tutorial!

https://youtu.be/ZUr2zrx7ZY8
