<?php
require_once __DIR__.'/includes/bootstrap.php';
require_role(['admin','librarian']);
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $id=(int)($_POST['id']??0);
    $name=trim($_POST['name']??'');
    $desc=trim($_POST['description']??'');
    if($name==='') { flash('error','Category name is required.'); redirect('categories.php'.($id?'?edit='.$id:'')); }
    try {
        if($id) {
            $s=$conn->prepare('UPDATE categories SET name=?,description=? WHERE id=?');
            $s->bind_param('ssi',$name,$desc,$id); $s->execute();
            log_activity($conn,(int)current_user()['id'],'category_updated','Category ID '.$id.': '.$name);
            flash('success','Category updated.');
        } else {
            $s=$conn->prepare('INSERT INTO categories(name,description) VALUES(?,?)');
            $s->bind_param('ss',$name,$desc); $s->execute();
            log_activity($conn,(int)current_user()['id'],'category_created',$name);
            flash('success','Category created.');
        }
    } catch(Throwable $e) { flash('error','Could not save category. Check whether the name already exists.'); }
    redirect('categories.php');
}
$editId=(int)($_GET['edit']??0);
$edit=['id'=>0,'name'=>'','description'=>''];
if($editId){$s=$conn->prepare('SELECT id,name,description FROM categories WHERE id=?');$s->bind_param('i',$editId);$s->execute();$found=$s->get_result()->fetch_assoc();if($found)$edit=$found;else{flash('error','Category not found.');redirect('categories.php');}}
$rows=$conn->query('SELECT c.*,COUNT(b.id) book_count FROM categories c LEFT JOIN books b ON b.category_id=c.id GROUP BY c.id ORDER BY c.name');
page_header('Categories');
?>
<div class="page-heading"><h1>Categories</h1></div>
<div class="split">
<section class="panel"><h2><?=$edit['id']?'Edit category':'Add category'?></h2>
<form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=(int)$edit['id']?>">
<label>Name *</label><input name="name" required maxlength="100" value="<?=e($edit['name'])?>">
<label>Description</label><textarea name="description" rows="3"><?=e($edit['description']??'')?></textarea>
<button class="btn"><?=$edit['id']?'Save changes':'Add category'?></button><?php if($edit['id']):?> <a class="btn secondary" href="categories.php">Cancel</a><?php endif;?></form></section>
<section class="panel"><h2>Categories</h2><div class="table-wrap"><table><thead><tr><th>Name</th><th>Books</th><th>Actions</th></tr></thead><tbody>
<?php while($c=$rows->fetch_assoc()):?><tr><td><?=e($c['name'])?></td><td><?=$c['book_count']?></td><td class="actions"><a class="btn secondary small-btn" href="categories.php?edit=<?=$c['id']?>">Edit</a><form method="post" action="actions/category_delete.php" data-confirm="Delete category? Books will become uncategorized."><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=$c['id']?>"><button class="btn danger small-btn">Delete</button></form></td></tr><?php endwhile;?>
</tbody></table></div></section></div><?php page_footer();?>
