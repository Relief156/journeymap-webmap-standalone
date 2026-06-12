你存放 Minecraft 地图数据的目录。

## 目录结构

```
data/
├── overworld/
│   └── day/          ← 放入 JourneyMap 生成的 .png 瓦片文件
│       ├── 0,0.png
│       ├── 0,1.png
│       └── ...
└── waypoints/        ← 放入 JourneyMap 生成的 .json 路径点文件
    ├── 路径点名称_100-64-200.json
    └── ...
```

## 如何获取数据

1. 在 Minecraft 中安装 [JourneyMap](https://www.curseforge.com/minecraft/mc-mods/journeymap) 模组
2. 进入游戏探索世界，JourneyMap 会自动生成地图数据
3. 数据默认存放在 `.minecraft/journeymap/data/` 目录下
4. 找到你的世界对应的文件夹，将 `overworld/day/` 和 `waypoints/` 复制到本目录

你也可以修改 `config.properties` 中的 `data_dir` 直接指向 JourneyMap 的数据目录。
