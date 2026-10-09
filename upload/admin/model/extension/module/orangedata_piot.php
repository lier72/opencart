<?php
/**
 * Admin model for the OrangeData ТС ПИоТ module.
 *
 * Thin wrapper delegating all table access to OrangeDataRepository (system library), so admin and the
 * catalog-side event share identical SQL. Scope: called from ControllerExtensionModuleOrangedataPiot.
 */
class ModelExtensionModuleOrangedataPiot extends Model {

    private $repo;

    /** Lazily builds the repository over the OpenCart DB object. */
    private function repo() {
        if (!$this->repo) {
            require_once DIR_SYSTEM . 'library/orangedata/OrangeDataRepository.php';
            $this->repo = new OrangeDataRepository($this->db);
        }
        return $this->repo;
    }

    public function install() { $this->repo()->install(); }
    public function uninstall() { /* preserve fiscal marking-code history */ }

    public function getProductMarking($product_id, array $categoryGroupMap = array()) { return $this->repo()->getProductMarking($product_id, $categoryGroupMap); }
    public function getMarkingOverride($product_id) { return $this->repo()->getMarkingOverride($product_id); }
    public function setProductMarking($product_id, $is_marked, $group_id) { $this->repo()->setProductMarking($product_id, $is_marked, $group_id); }
    public function deleteProductMarking($product_id) { $this->repo()->deleteProductMarking($product_id); }

    public function getVariantEans(array $pov_ids) { return $this->repo()->getVariantEans($pov_ids); }
    public function getProductEans(array $product_ids) { return $this->repo()->getProductEans($product_ids); }

    public function getOrderMarkingCodes($order_id) { return $this->repo()->getOrderMarkingCodes($order_id); }
    public function saveScannedCode(array $data) { return $this->repo()->saveScannedCode($data); }
    public function saveCheckResult($id, array $r) { $this->repo()->saveCheckResult($id, $r); }
    public function deleteCode($id) { $this->repo()->deleteCode($id); }
}
