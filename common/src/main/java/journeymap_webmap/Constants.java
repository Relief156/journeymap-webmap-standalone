package journeymap_webmap;

import net.minecraft.client.Minecraft;
import net.minecraft.resources.Identifier;

import java.io.IOException;
import java.io.InputStream;

public interface Constants
{
    String MOD_ID = "journeymap_webmap";

    static InputStream getResourceAsStream(Identifier identifier) throws IOException
    {
        return Minecraft.getInstance().getResourceManager().open(identifier);
    }
}
