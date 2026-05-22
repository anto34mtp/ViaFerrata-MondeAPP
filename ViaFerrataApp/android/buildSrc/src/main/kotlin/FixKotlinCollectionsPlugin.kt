import com.android.build.api.instrumentation.AsmClassVisitorFactory
import com.android.build.api.instrumentation.ClassContext
import com.android.build.api.instrumentation.ClassData
import com.android.build.api.instrumentation.FramesComputationMode
import com.android.build.api.instrumentation.InstrumentationParameters
import com.android.build.api.instrumentation.InstrumentationScope
import com.android.build.api.variant.AndroidComponentsExtension
import org.gradle.api.Plugin
import org.gradle.api.Project
import org.objectweb.asm.ClassVisitor
import org.objectweb.asm.MethodVisitor
import org.objectweb.asm.Opcodes

/**
 * Patches react-native-screens bytecode to replace Kotlin 1.9.20+ direct
 * invokeinterface calls to List.removeFirst() / List.removeLast() with
 * equivalent removeAt(0) / removeAt(lastIndex) calls, fixing crashes on
 * Android < API 35 (which doesn't have SequencedCollection methods on List).
 */
abstract class FixCollectionsFactory : AsmClassVisitorFactory<InstrumentationParameters.None> {

    override fun createClassVisitor(
        classContext: ClassContext,
        nextClassVisitor: ClassVisitor,
    ): ClassVisitor = object : ClassVisitor(Opcodes.ASM9, nextClassVisitor) {

        override fun visitMethod(
            access: Int, name: String, descriptor: String,
            signature: String?, exceptions: Array<out String>?,
        ): MethodVisitor {
            val base = super.visitMethod(access, name, descriptor, signature, exceptions)
            return object : MethodVisitor(Opcodes.ASM9, base) {
                override fun visitMethodInsn(
                    opcode: Int, owner: String, name: String,
                    descriptor: String, isInterface: Boolean,
                ) {
                    if (isInterface
                        && opcode == Opcodes.INVOKEINTERFACE
                        && descriptor == "()Ljava/lang/Object;"
                        && (name == "removeFirst" || name == "removeLast")
                    ) {
                        // Stack before: [..., listRef]
                        if (name == "removeFirst") {
                            // list.remove(0)
                            mv.visitInsn(Opcodes.ICONST_0)
                            mv.visitMethodInsn(
                                Opcodes.INVOKEINTERFACE, owner,
                                "remove", "(I)Ljava/lang/Object;", true,
                            )
                        } else {
                            // list.remove(list.size() - 1)
                            mv.visitInsn(Opcodes.DUP)
                            mv.visitMethodInsn(
                                Opcodes.INVOKEINTERFACE, owner, "size", "()I", true,
                            )
                            mv.visitInsn(Opcodes.ICONST_1)
                            mv.visitInsn(Opcodes.ISUB)
                            mv.visitMethodInsn(
                                Opcodes.INVOKEINTERFACE, owner,
                                "remove", "(I)Ljava/lang/Object;", true,
                            )
                        }
                        return
                    }
                    super.visitMethodInsn(opcode, owner, name, descriptor, isInterface)
                }
            }
        }
    }

    override fun isInstrumentable(classData: ClassData): Boolean =
        classData.className.startsWith("com.swmansion.rnscreens")
}

class FixKotlinCollectionsPlugin : Plugin<Project> {
    override fun apply(project: Project) {
        val androidComponents =
            project.extensions.getByType(AndroidComponentsExtension::class.java)
        androidComponents.onVariants(androidComponents.selector().all()) { variant ->
            variant.instrumentation.transformClassesWith(
                FixCollectionsFactory::class.java,
                InstrumentationScope.ALL,
            ) {}
            variant.instrumentation.setAsmFramesComputationMode(
                FramesComputationMode.COMPUTE_FRAMES_FOR_INSTRUMENTED_METHODS,
            )
        }
    }
}
