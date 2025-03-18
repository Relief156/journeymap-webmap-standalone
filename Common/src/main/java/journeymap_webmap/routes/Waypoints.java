package journeymap_webmap.routes;

import com.mojang.blaze3d.platform.NativeImage;
import io.javalin.http.ContentType;
import io.javalin.http.Context;
import journeymap.client.texture.TextureCache;

import java.io.IOException;
import java.nio.channels.Channels;

public class Waypoints
{
    public static void iconGet(Context ctx)
    {
        String id = ctx.pathParam("id");

        NativeImage img = TextureCache.getColorizedWaypointIcon(id).getPixels();

        if (img != null && img.pixels > 0)
        {
            try (var channel = Channels.newChannel(ctx.outputStream()))
            {
                ctx.contentType(ContentType.IMAGE_PNG);
                img.writeToChannel(channel);
                ctx.outputStream().flush();
            }
            catch (IOException e)
            {
                // nothing
            }
        }
    }


}
