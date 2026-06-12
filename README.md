# JourneyMap PHP Viewer

一款纯 PHP 编写的 Minecraft JourneyMap 网页地图查看器，无需 Java 运行环境，可直接部署在任何 PHP 虚拟主机上。

## 功能特性

- **纯 PHP** — 零依赖，不依赖任何第三方库或框架
- **即插即用** — 上传即可使用，Apache / nginx / IIS 全兼容
- **单文件架构** — 核心功能集中在一个 `index.php` 中
- **Canvas 渲染** — 流畅的瓦片地图浏览，支持拖拽、滚轮缩放、触摸操作
- **路径点系统** — 自动解析 JourneyMap 路径点，按类别筛选
- **菱形图标** — 路径点使用菱形标记，白色底座 + 彩色填充，暗色地图上清晰可辨
- **自定义标签颜色** — 面板中可自由选择路径点标签颜色
- **不透明度控制** — 滑块调节路径点整体不透明度 (10%~100%)
- **ETag 缓存** — 瓦片图片支持 304 缓存，减少带宽消耗
- **响应式** — 自适应窗口大小

### API 端点

| 端点 | 说明 |
|------|------|
| `index.php` | 完整的地图查看器页面 |
| `index.php?api=info` | JSON，返回瓦片大小和瓦片列表 |
| `index.php?api=waypoints` | JSON，返回所有路径点数据 |
| `index.php?api=tiles&tile=0,0.png` | PNG 图片，返回指定瓦片 |

## 环境要求

- PHP 7.4 及以上
- 任意 Web 服务器（Apache、nginx、IIS 或 PHP 内置服务器）

## 快速开始

### 1. 获取地图数据

首先确保你已经在 Minecraft 中安装并使用 [JourneyMap](https://www.curseforge.com/minecraft/mc-mods/journeymap) 模组探索过世界。

JourneyMap 的数据默认存放在：
```
.minecraft/journeymap/data/[服务器类型]/[世界名]/
```

将该目录下的 `overworld/day/` 和 `waypoints/` 复制到本项目的 `data/` 目录：

```
data/
├── overworld/
│   └── day/          ← PNG 瓦片文件
└── waypoints/        ← JSON 路径点文件
```

### 2. 复制项目文件

将以下文件复制到你的 Web 服务器目录：

```
项目根目录/
├── index.php          核心文件
├── config.properties  配置文件
└── data/              地图数据目录
    ├── overworld/
    │   └── day/
    └── waypoints/
```

### 3. 配置

编辑 `config.properties`，修改 `data_dir` 指向你的地图数据目录：

```properties
# 相对路径（相对于 index.php 所在目录）
data_dir=data

# 或者绝对路径
# data_dir=C:/path/to/journeymap/data/mp/MyWorld
```

### 4. 启动

**方式一：PHP 内置服务器（本地测试）**
```bash
php -S localhost:8080 -t /path/to/project
```

**方式二：虚拟主机（Apache / nginx）**
直接上传到网站目录，访问 `http://你的域名/index.php` 即可。

> **注意**：本项目使用查询参数方式路由，不依赖 URL 重写，在 Apache、nginx、IIS 上均可即传即用。

## 配置说明

`config.properties` 中的全部选项：

| 参数 | 说明 | 默认值 |
|------|------|--------|
| `data_dir` | 地图数据目录路径（相对或绝对） | `data` |

## 项目结构

```
.
├── index.php              # 核心文件（路由 + API + HTML 页面）
├── config.properties      # 配置文件
├── LICENSE                # MIT 许可证
├── README.md              # 项目说明
├── .gitignore             # Git 忽略规则
├── data/                  # 地图数据目录（不包含在版本控制中）
│   ├── overworld/
│   │   └── day/           # PNG 瓦片文件
│   └── waypoints/         # JSON 路径点文件
└── java版/                # 原始 Java 版本（参考用）
    └── src/               # Java 源码
```

## 技术细节

### 瓦片加载

- 瓦片尺寸：512×512 像素
- 懒加载：仅加载视口范围内的瓦片
- 失败缓存：加载失败的瓦片不会重复请求
- 内存管理：最多缓存 256 张瓦片，超出自动淘汰

### 坐标系统

- 世界坐标的 X/Z 轴对应瓦片的横纵方向
- 瓦片命名格式：`{x},{z}.png`（如 `0,3.png`）

### 兼容性

- 桌面端：鼠标拖拽 + 滚轮缩放
- 移动端：触摸拖拽 + 双指缩放
- 支持 IE11+ 及所有现代浏览器

## 从 Java 版迁移

本项目最初是 [JourneyMap](https://www.curseforge.com/minecraft/mc-mods/journeymap) 模组自带的 Java 独立地图服务器。原始 Java 代码保留在 `java版/` 目录中作为参考。

PHP 版本完全重新实现，使用相同的 API 格式，但不需要 Java 运行时。

## 许可

本项目基于 MIT 许可证开源。详见 [LICENSE](LICENSE) 文件。

- JourneyMap 是 [techbrew](https://www.curseforge.com/members/techbrew) 开发的 Minecraft 模组
- 本项目从journeymap-webmap fork而来 使用它的核心代码 作为基础
- 本项目在 PHP 中实现，不依赖于 Java 运行时
